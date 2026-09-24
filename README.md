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

## Floorplan source of truth

The database is the authoritative source for the floorplan. Runtime code must not carry
its own copies of coordinates or measured corrections.

- `house_floors` stores floor dimensions and the explicit display-mirroring flag.
- `house_rooms` stores room labels, polygons, floor material and stair-opening metadata.
- `house_walls` stores named wall segments, thickness and exterior-layer direction.
- `furniture_items` stores furniture identity, render metadata, position, rotation,
  dimensions and mounting height.
- `furniture_layouts` only tracks model revision/build state; it no longer contains a
  second JSON copy of furniture values.

Laravel exposes the house through `/dashboard/house`. Vue/Three.js uses that response
for navigation, room polygons, measurement maps and display orientation. The Blender
worker receives the same database geometry together with the same database furniture
in one build payload. `assets/blender/build_house.py` is therefore a renderer, not a
second floorplan definition.

`resources/js/house.json` and `assets/blender/furniture.json` are retained as historical
trace/bootstrap material only. They are not runtime sources of truth. The furniture JSON
is read by the one-time migration that seeds `furniture_items` on a fresh installation;
production values already present in the database take precedence during that migration.

Future geometry or furniture corrections belong in Laravel migrations. Avoid adding
house-specific coordinates to Vue, TypeScript or Blender scripts.

## House model

See `docs/project-decisions.md`. Ground floor internal dimensions are 11 × 5.5 m;
upper floor and attic are 8 × 5.5 m. The current effective room polygons and wall
segments are stored in the database, including measured corrections that were formerly
applied separately in the frontend and Blender scripts.

The model shows a 1.3 m cutaway. Full ceiling height is provisionally 2.6 m.
Exterior walls add provisional 0.10 m sand-lime brick, 0.12 m insulated cavity and
0.10 m outer brick layers outside the internal footprint. Partition thickness is stored
per wall in the database. These are **modelling assumptions, not measured construction
details** unless a specific measurement has been recorded. No Dutch building-standard
compliance is implied.

Floor models are generated through the Laravel furniture worker because Blender now
requires the database-backed input payload:

```sh
php artisan floorplan:rebuild
```

Generated models are published as `public/models/{ground,upper,attic}.glb` for the base
models or from `storage/app/furniture/<revision>` for revisioned builds. Original private
reference files in `assets/source` are ignored by Git and excluded from Docker images.

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

The remaining confirmed mappings are Utility → Serverkast and attic hallway → Zolder.

### Meubels aanpassen

Open **Meubels**, selecteer een nummer en vul breedte, diepte, hoogte en de onderkant vanaf de vloer in centimeters in. Je kunt meerdere meubels op verschillende verdiepingen aanpassen en samen opslaan. De knop **Opslaan en plattegrond bijwerken** slaat de maten in `furniture_items` op en zet één Blender-bouw in de wachtrij. Het vorige model blijft beschikbaar tot alle nieuwe modellen gereed zijn. De status blijft na sluiten of herladen behouden. Bij een fout kun je opnieuw opbouwen.

DDEV installeert Blender en start de worker via `.ddev/config.furniture.yaml`; voer na ophalen van nieuwe migraties `ddev exec php artisan migrate --force` en zo nodig `ddev restart` uit. Docker Compose bevat een aparte `furniture-worker` met dezelfde database en storage-volume als de webapp. Voer bij deployment altijd de Laravel-migraties uit. Er is geen Node-build nodig na het wijzigen van meubelmaten via de editor.

Gegenereerde modellen en logs staan onder `storage/app/furniture/<revisie>`. Maak een back-up van zowel de database als het storage-volume. `BLENDER_BINARY` kan het Blender-pad overschrijven (standaard `/usr/bin/blender`). Bouwlogs zijn alleen lokaal beschikbaar. De editor wijzigt maten rond het bestaande middelpunt; plaatsing en wandafstand worden niet automatisch opnieuw berekend. De hoge meubels behouden de bestaande afsnijding op muurhoogte.
