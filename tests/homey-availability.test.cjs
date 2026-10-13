const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('docs/homey/ikea-availability.js', 'utf8');

async function run({ device, responses = [{ ok: true, status: 201 }] } = {}) {
  const calls = [];
  const waits = [];
  const script = source.replace("const WRITE_TOKEN = '';", "const WRITE_TOKEN = 'local-test-token-with-32-characters';")
    .replace('const DEVICE_IDS = [];', "const DEVICE_IDS = ['ikea-1'];");
  const context = {
    Homey: {
      devices: { getDevices: async () => ({ 'ikea-1': device ?? { id: 'ikea-1', name: 'Lamp', zone: 'room', driverId: 'actual-driver', available: false } }) },
      zones: { getZones: async () => ({ room: { name: 'Living' } }) },
    },
    log: () => {}, wait: async delay => waits.push(delay),
    fetch: async (url, options) => {
      calls.push({ url, options });
      const response = responses[Math.min(calls.length - 1, responses.length - 1)];
      if (response instanceof Error) throw response;
      return response;
    },
  };
  const result = vm.runInNewContext(`(async () => { ${script} })()`, context);
  return { result, calls, waits };
}

test('reports false availability with actual driver and zone metadata', async () => {
  const { result, calls } = await run();
  assert.equal(await result, true);
  const body = JSON.parse(calls[0].options.body);
  assert.deepEqual(body.devices[0], {
    device_id: 'ikea-1', name: 'Lamp', zone: 'Living', driver: 'actual-driver',
    available: false, observed_at: body.devices[0].observed_at,
  });
  assert.equal(calls[0].options.redirect, 'error');
});

test('retries a lost response with an identical payload', async () => {
  const { result, calls, waits } = await run({ responses: [new Error('network'), { ok: true, status: 200 }] });
  assert.equal(await result, true);
  assert.equal(calls.length, 2);
  assert.equal(calls[0].options.body, calls[1].options.body);
  assert.deepEqual(waits, [2000]);
});

test('unknown availability aborts without sending a fabricated offline sample', async () => {
  const { result, calls } = await run({ device: { id: 'ikea-1', name: 'Lamp', driverId: 'actual-driver' } });
  await assert.rejects(result, /no usable availability metadata/);
  assert.equal(calls.length, 0);
});

test('authentication failure throws without retrying', async () => {
  const { result, calls } = await run({ responses: [{ ok: false, status: 401 }] });
  await assert.rejects(result, /HTTP 401/);
  assert.equal(calls.length, 1);
});
