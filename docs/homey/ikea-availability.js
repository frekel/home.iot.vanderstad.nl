// Copy into a NEW HomeyScript. Configure only in Homey, never commit the token.
const ENDPOINT = 'https://home.iot.vanderstad.nl/api/v1/availability/snapshots';
const WRITE_TOKEN = '';
if (!ENDPOINT.startsWith('https://') || WRITE_TOKEN.length < 32) {
  throw new Error('Configure HTTPS and the write token first.');
}
const devices = await Homey.devices.getDevices();
const matterDevices = Object.values(devices)
  .filter(device =>
    (
      device.driverUri?.toLowerCase().includes('matter') ||
      device.driverId?.toLowerCase().includes('matter')
    ) &&
    device.driverId?.toLowerCase().includes('ikea')
  )
  .sort((a, b) => a.name.localeCompare(b.name));

if (matterDevices.length === 0 || matterDevices.length > 500) {
  throw new Error('The IKEA Matter selection must contain 1–500 devices.');
}
const measurements = [];
for (const device of matterDevices) {
  if (typeof device.available !== 'boolean' || !device.driverId || !device.name) {
    throw new Error(`Device ${device.id} has no usable availability metadata.`);
  }
  const deviceZone = await device.getZone();
  measurements.push({
    device_id: device.id,
    name: device.name,
    zone: deviceZone?.name ?? null,
    driver: device.driverId,
    available: device.available,
    observed_at: new Date().toISOString(),
  });
  if (device.available === false) {
    log(`${device.name} in ${deviceZone?.name ?? 'unknown zone'} is not available`);
  }
}
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
