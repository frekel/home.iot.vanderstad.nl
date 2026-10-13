// Copy into a NEW HomeyScript. Configure only in Homey, never commit the token.
const ENDPOINT = 'https://home.iot.vanderstad.nl/api/v1/availability/snapshots';
const WRITE_TOKEN = '';
// Explicitly select your IKEA Matter devices after inspecting getDevices().
const DEVICE_IDS = [];

if (!ENDPOINT.startsWith('https://') || WRITE_TOKEN.length < 32 || DEVICE_IDS.length === 0) {
  throw new Error('Configure HTTPS, the write token and IKEA Matter device IDs first.');
}
if (DEVICE_IDS.length > 500 || new Set(DEVICE_IDS).size !== DEVICE_IDS.length) {
  throw new Error('Select 1–500 distinct device IDs.');
}
const devices = await Homey.devices.getDevices();
const zones = await Homey.zones.getZones();
const observedAt = new Date().toISOString();
const measurements = DEVICE_IDS.map(id => {
  const device = devices[id];
  if (!device || typeof device.available !== 'boolean' || !device.driverId || !device.name) {
    throw new Error(`Device ${id} is missing or has no usable availability metadata.`);
  }
  return {
    device_id: device.id,
    name: device.name,
    zone: zones[device.zone]?.name ?? null,
    driver: device.driverId,
    available: device.available,
    observed_at: observedAt,
  };
});
// Reuse the exact payload on retries, including timestamps.
const body = JSON.stringify({ devices: measurements });
for (let attempt = 0; attempt < 3; attempt++) {
  let response;
  try {
    response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${WRITE_TOKEN}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body,
      redirect: 'error',
      timeout: 15000,
    });
  } catch (error) {
    if (attempt === 2) throw new Error('Availability upload failed after three attempts. Check network and TLS.');
    await wait(2000 * (attempt + 1));
    continue;
  }
  if (response.ok) {
    log(`Availability: ${measurements.length} devices sent successfully.`);
    return true;
  }
  const retryable = response.status === 429 || response.status >= 500;
  if (!retryable || attempt === 2) {
    throw new Error(`Availability upload refused (HTTP ${response.status}). Check configuration and server logs.`);
  }
  await wait(response.status === 429 ? 60000 : 2000 * (attempt + 1));
}
