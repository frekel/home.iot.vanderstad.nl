# Van der Stad Home

Desktop smart-home dashboard built with Laravel, Vue, TypeScript, Three.js and Blender.

## Current milestone

Working dashboard with three floor models traced from the supplied SVG backgrounds.

- Ground-floor living room: one explicitly mapped **live light** and one live climate sensor.
- States refresh every five seconds (HTTP polling, not a WebSocket subscription yet).
- The switch waits for Homey's reported state; failed/offline commands show an error.
- Temperature/humidity show the source sensor and reading age, with no invented history.
- Other rooms and energy charts remain clearly labelled sample previews.
- Connection details show discovered devices and zones. No API key reaches the browser.

Local verification on 2026-09-15: `Staande lamp` was switched off through the dashboard,
read back from Homey, then restored on. `Inside` supplies living-room climate readings.
Those mappings live in the ignored environment file, not hardcoded device IDs.

## Development

```sh
ddev start
ddev composer install
# On a fresh checkout: cp .env.example .env, then ddev artisan key:generate
ddev artisan migrate
ddev npm ci
ddev npm run build
ddev launch
```

DDEV uses PHP 8.4 and MariaDB 11.8. This machine uses the alternate HTTPS port 33001
because ports 80/443 are already occupied. The running URL is shown by `ddev describe`.

```sh
ddev npm run typecheck
ddev artisan test
```

## House model

See `docs/project-decisions.md`. Ground floor internal dimensions are 11 × 5.5 m;
upper floor and attic are 8 × 5.5 m. `resources/js/house.json` contains approximate
room polygons and wall segments traced from the embedded plan images. Device markers
in the original SVG use older entity names and are not yet mapped to Homey IDs.

The first model shows a 1.3 m cutaway. Full ceiling height is provisionally 2.6 m.
Exterior walls add provisional 0.10 m sand-lime brick, 0.12 m insulated cavity and
0.10 m outer brick layers outside the internal footprint. Partitions are provisionally
0.10 m. These are **modelling assumptions, not measured construction details**.
Actual openings, roof slopes, wall junctions and furniture remain to be refined. No Dutch building-standard compliance is implied.

```sh
blender --background --python assets/blender/build_house.py
```

This produces `assets/blender/house.blend` and `public/models/{ground,upper,attic}.glb`.
Original private reference files in `assets/source` are ignored by Git and excluded
from Docker images. The traced model is included in the repository.

## Home server / Traefik

Target: Intel NUC8 i7BEH, Ubuntu 24.04.4 LTS, Docker Compose, existing Traefik.

1. Confirm the existing Traefik container is connected to `docker_default` (or change
   `TRAEFIK_NETWORK`). This default is inferred from homelab's Compose project `docker`.
2. Ensure Yuvomi's old `home.iot.vanderstad.nl` router is removed from the running stack.
3. Point local DNS for `home.iot.vanderstad.nl` to the NUC's LAN address.
4. Existing Traefik certificates must cover this hostname. This stack uses `websecure`
   and `tls=true`, matching the homelab override's file-provider certificates.
5. Copy `.env.production.example` to `.env.production`. Set a unique Laravel APP_KEY
   (`ddev artisan key:generate --show`), database passwords, and your actual desktop
   LAN subnet in `LAN_ALLOWED_CIDRS`. Do not use the whole Docker subnet as an allowlist.
6. Build and initialize:

```sh
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d database
docker compose --env-file .env.production run --rm app php artisan migrate --force
docker compose --env-file .env.production up -d
```

No app or database ports are published directly. Traefik's router has an explicit LAN
IP allowlist. Validate allowed LAN access and denied non-LAN access on the NUC before
considering deployment complete. If another proxy sits in front of Traefik, review the
client-IP handling rather than widening the allowlist.

Back up the MariaDB database and storage volume before upgrades. Keep `.env.production`
backed up separately and private. Homey credentials are server-side environment values;
never prefix them with `VITE_` or commit them. Set `HOMEY_LIVING_LIGHT_ID` and `HOMEY_LIVING_CLIMATE_ID` to explicitly link devices.
Only the configured light can be switched, and the backend checks it is an available,
writable light before sending a command. The route uses Laravel session CSRF protection.
Other room controls and energy charts remain previews. Both mapping variables are
passed through Compose. The setup currently assumes access only from the trusted LAN;
add application login before expanding access beyond the household network.

## Homey room names

Floor and room labels come from Homey's zone API. `config/homey_layout.php` binds stable
zone IDs to model IDs; renaming a zone in Homey updates the dashboard within a minute.
The connection panel also mirrors the full Homey hierarchy, including zones outside
the current 3D model. During an outage, the open page retains the last received names.

Entrance and Dining are grouped with Woonkamer: one navigation entry and shared 3D
selection. Upper-floor positions confirmed by the user: left bedroom Slaapkamer,
right bedroom Levi, lower-left Bijspringer, lower-right Badkamer; the hallway is
Eerste verdieping. Room labels do not expand the live-device control allowlist.
