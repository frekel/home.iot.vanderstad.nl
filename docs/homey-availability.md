# IKEA Matter availability from Homey Pro 2023

This is independent of the dashboard polling and the existing daily battery check.
It adds no Homey capability commands and does not modify an existing Flow.

## Network and credentials

Use local HTTPS, as agreed. Homey must resolve `home.iot.vanderstad.nl` to the LAN
reverse proxy and trust its TLS certificate (valid hostname and full certificate chain).
No public port forwarding or deployment change is included. Keep the application port
accessible only from the trusted reverse proxy: the existing Laravel configuration
trusts proxy headers from all proxies. A directly exposed application port would let
clients spoof HTTPS and the IP used for throttling. HTTP is rejected, not redirected.

On the server, configure two **different**, independently generated random tokens of
at least 32 characters in the ignored environment file:

```dotenv
AVAILABILITY_WRITE_TOKEN=
AVAILABILITY_READ_TOKEN=
```

The blank defaults fail closed. These are separate from `HOMEY_API_KEY`.
Use a password manager to generate and store the actual values. Do not put them in
Git, URLs, browser bundles, Flow tags or logs. The write token authorizes only ingestion;
the read token authorizes only the read APIs. Rotation means replacing the corresponding
server environment value and its client copy, then refreshing Laravel's configuration
cache. Keep HomeyScript editing access restricted; its locally configured token is
visible to people with that access. This implementation is for one Homey/household.

After review and an explicit deployment decision, back up the database, run
`php artisan migrate`, set the tokens and refresh cached configuration with
`php artisan config:cache`. No migration or deployment is performed by this PR.
New tables are isolated from the existing inventory, geometry and battery functionality.
Rolling back the migration deletes monitoring history; export it before rollback.

## Ingestion

`POST /api/v1/availability/snapshots`, with `Authorization: Bearer <write token>`,
`Content-Type: application/json` and `Accept: application/json`:

```json
{
  "devices": [
    {
      "device_id": "example-device-id",
      "name": "IKEA lamp",
      "zone": "Living room",
      "driver": "copy-the-actual-driverId-from-Homey",
      "available": true,
      "observed_at": "2026-10-13T10:00:00Z"
    }
  ]
}
```

Send 1–500 distinct devices per request. IDs are max 128 ASCII letters, digits,
underscores or hyphens; names and driver identifiers are nonempty strings of max 255
characters. `zone` is required but may be null. `available` must be a JSON boolean:
strings and integers are rejected. `observed_at` is an ISO 8601 timestamp with a timezone
(`Z` or an offset), optional 1–3 fractional digits, and at most five minutes in the
future. Timestamps are stored and returned in UTC, at whole-second precision.
Unknown fields in a device object are rejected.

A batch commits atomically. `201` returns `inserted` and `duplicates` counts; an exact
retry returns `200` and does not create snapshots or transitions again. A different
payload for the same device and UTC second returns `409`. Older new observations
return `422`; they are not backfilled. Retry the exact body after a network failure or
`5xx`; do not regenerate timestamps. The first sample establishes a baseline and
creates no transition, including when first observed offline. Later boolean changes
create transitions recording both observation times. Per-device locks and deterministic
lock ordering serialize concurrent writers on MariaDB; unique constraints prevent
duplicate snapshots. SQLite tests exercise batch behavior, not MariaDB row locking.

Other errors: `401` invalid/missing token, `403` HTTP, `415` wrong content type,
`422` validation, `429` rate limit, `503` missing/weak/equal token configuration.
Ingestion is limited to 12 requests/minute per client IP; reads to 60/minute.

## Read APIs

All require `Authorization: Bearer <read token>` and HTTPS; successful responses use
`Cache-Control: no-store`. Responses use Laravel pagination (`data`, `total`,
`current_page`, `next_page_url`, etc.). Default page size is 50, maximum 100.

| GET path | Result |
| --- | --- |
| `/api/v1/availability/devices` | Latest known metadata, boolean status and `stale` per device |
| `/api/v1/availability/history` | Snapshots, newest observation first |
| `/api/v1/availability/transitions` | Online/offline changes with `previous_observed_at` and `observed_at` |
| `/api/v1/availability/report` | Per-device counts of total, online and offline samples, plus `online_sample_percent` |

All accept `device_id`, `page` and `per_page`. History, transitions and report accept
inclusive `from`/`to` timestamps with timezones. Default period is the last 30 days;
a requested period may span at most 366 days. `from` and `to` may each be provided
separately; the omitted bound takes its default. Latest status ignores period filters.
An unknown device gives an empty result. Historical metadata stays attached to its
snapshot when the name, zone or driver changes.

`stale` becomes true more than 90 minutes after the last observation; the last known
`available` value stays unchanged. Missing devices or failed uploads do not imply
offline, and missing samples are not added to reports. Hourly snapshots only detect
state changes between measurements. A transition happened somewhere between its
previous and current observation, not necessarily at the current timestamp.
`online_sample_percent` is a proportion of received measurements, **not measured
uptime**. Brief outages between polls can be missed. Data is retained indefinitely;
monitor table growth and choose a retention policy before adding any pruning job.

## A separate hourly Homey Flow

1. Install/open HomeyScript on Homey Pro 2023. Inspect devices with this read-only
   snippet in a temporary script (no credentials needed):

   ```js
   const devices = await Homey.devices.getDevices();
   for (const d of Object.values(devices)) {
     log({ id: d.id, name: d.name, driver: d.driverId, zone: d.zone, available: d.available });
   }
   ```

2. The upload script automatically selects devices where `driverUri` includes
   `matter`, or the lowercase `driverId` includes `matter` or `ikea`, then sorts by
   name. This is the requested broad selection: it may include non-IKEA Matter
   devices and IKEA Zigbee devices. Review the matched devices in Homey.
   The server does not independently verify manufacturer or protocol.
3. Create a **new** script named `IKEA Matter availability upload` from
   [ikea-availability.js](homey/ikea-availability.js). Set `WRITE_TOKEN` to the real
   write token locally in Homey only and check the HTTPS endpoint. No device ID list
   is required. The script obtains each zone with `device.getZone()` and logs offline
   devices. Unknown availability or a failed zone lookup aborts the upload rather
   than manufacturing a status. It sends the API's six fields; `class` and
   `lastSeenAt` are not sent. `observed_at` is the time of this availability reading,
   not the device's last sensor update.
4. Test once manually; expect a success log and check the read API using the read
   token. Check the `driver`, zone and available values against Homey.
5. Create a **new** Flow named `IKEA Matter availability hourly`: use the Date & Time
   trigger for every hour and the HomeyScript action to run this new script. Card
   wording can vary by language/version. Do not reuse or change the daily battery Flow.
6. Connect the script's failure/error output to your chosen local handling if desired.
   The script throws on failure and retries transient HTTP/network failures up to
   three times with the same body. HTTP 429 waits one minute. Nothing is silently
   reported offline. Confirm the hourly Flow execution history and latest timestamps
   after two hours. The local HTTPS and Homey integration require this manual check;
   automated tests do not contact your Homey.

The script uses Homey's [HomeyScript global APIs](https://athombv.github.io/com.athom.homeyscript/global)
and the modern combined `driverId` described in Athom's
[migration guide](https://github.com/athombv/com.athom.homeyscript/blob/master/docs/tutorials/migrate.md).
Laravel's [API routing](https://laravel.com/framework/docs/13.x/routing) supplies a
stateless route group separate from web sessions and CSRF-protected browser routes.

## Validation

Run `php artisan test --compact --filter=AvailabilityTest`, then the full existing
suite with `php artisan test --compact`. The feature tests cover authentication scope,
HTTPS, fail-closed configuration, strict payload validation, idempotency and atomic
conflict handling, transitions, pagination, report values, stale status and throttling.
Run `node --test tests/homey-availability.test.cjs` for the HomeyScript contract tests.
No external Homey calls or real credentials are used in these tests.
