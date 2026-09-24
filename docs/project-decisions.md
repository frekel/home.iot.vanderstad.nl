# Home dashboard — agreed scope

- Laravel in DDEV; Vue, TypeScript, Vite and Three.js frontend.
- Blender source and GLB exports for the interactive house.
- Homey Pro 2023 with server-side API key, never committed or sent to browser.
- Ubuntu 24.04.4 LTS on Intel NUC8 i7BEH, 16 GB RAM; Docker Compose deployment.
- Desktop first; local network only; intended hostname home.iot.vanderstad.nl.
- Existing Traefik: websecure entrypoint, TLS enabled, certificates via file provider.
- Homelab Compose project name docker implies docker_default network; verify on server before deployment.
- User reports removing Yuvomi from the homelab on 2026-09-15; use home.iot.vanderstad.nl. Connector read still showed the earlier revision; verify running router before deployment.

## House

The original layout was traced from the user-provided `floorplan.svg`, but the **database is now the single runtime source of truth**. The trace files and old JSON files may remain as historical/bootstrap material; frontend and Blender code must not contain their own copies of measured coordinates.

Authoritative runtime tables:

- `house_floors`: floor dimensions and display orientation.
- `house_rooms`: room polygons and render metadata.
- `house_walls`: named wall segments, thickness and exterior-layer direction.
- `furniture_items`: furniture identity, render metadata, position, rotation and dimensions.

Geometry changes and one-time data corrections are made with Laravel database migrations. Vue/Three.js reads `/dashboard/house`; the Blender worker receives the same database-backed house geometry and furniture in its build payload. Display mirroring is a floor property and must never be inferred from visual left/right wording.

All supplied dimensions are internal wall-to-wall, in metres:

| Floor | Width | Length |
| --- | --- | --- |
| Ground, including extension | 5.5 | 11 |
| Upper | 5.5 | 8 |
| Attic | 5.5 | 8 |

External walls: outer brick leaf, insulated cavity, inner sand-lime brick leaf.
Provisional modelling dimensions are approved; document them individually as assumptions, not Dutch building standards.
Extension location must be checked against the drawing. Roof and floor heights are unmeasured.

## Milestones

1. Development environment and dashboard with explicitly labelled sample data.
2. Scaled house model with floor selection and camera controls.
3. Homey connection, one light and sensor, then room/device mapping and realtime updates.
4. Available energy history and other sensor panels.
5. Docker image and Traefik deployment verified locally before server installation.

## First live integration — 2026-09-15

- Homey connection verified; 314 devices discovered at verification time.
- User selected Staande lamp in Woonkamer for the first controllable light.
- Inside in Woonkamer supplies temperature and humidity; display source and reading age.
- IDs live in ignored environment variables; credentials remain server-side.
- Five-second polling is the first synchronization implementation. Socket.IO is deferred.
- Off/on test passed; the lamp was restored to its initial on state.
- Remaining: more room mappings, energy history, model refinement, NUC deployment.
