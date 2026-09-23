# Database source of truth

The production database is the only runtime source of truth for house geometry and furniture placement.

## Authoritative tables

### `house_floors`

Stores floor identity, dimensions, sort order and the explicit `display_mirrored` presentation flag. Mirroring is a display concern and must never change the stored world coordinates.

### `house_rooms`

Stores room identity, labels, polygons, floor material and whether Blender renders a floor surface. A room can reference the furniture item used to cut a stair opening.

### `house_walls`

Stores named wall segments as world coordinates, wall thickness and the optional exterior direction used to build the outer wall layers. Names describe relationships (`bedroom-bathroom-partition`) rather than visual left/right orientation.

### `furniture_items`

Stores furniture identity and render metadata together with `x`, `y`, `rotation`, `width`, `depth`, `height` and `base_z`. Item-specific visual data such as a material override belongs in the metadata column, not in Blender ID checks.

### `furniture_layouts`

Only stores build/revision state. It is not a second copy of furniture values.

## Runtime data flow

```text
                        MariaDB
                           │
              ┌────────────┴────────────┐
              │                         │
         HouseLayout              FurnitureLayout
              │                         │
              ├──── /dashboard/house    ├──── /dashboard/furniture
              │                         │
              └────────────┬────────────┘
                           │
                 BuildFurniture payload
                           │
                           ▼
                         Blender
```

The Vue dashboard and measurement mode load the house from `/dashboard/house`. The Blender worker receives the same house rows and furniture rows in its input JSON. There are no runtime geometry corrections in TypeScript or Blender.

## Coordinate rule

Stored coordinates always use one world-coordinate system. UI mirroring is controlled by `house_floors.display_mirrored`; it does not mutate, reinterpret or rewrite coordinates. Avoid phrases such as "left wall" in migrations when a semantic relationship name is available.

## Changes

Geometry and one-time data corrections are made with Laravel database migrations. A migration should update the authoritative row directly. Do not add temporary Artisan commands for data corrections.

After a geometry migration, rebuild the floorplan so the published GLBs represent the new database state.

## Legacy files

`resources/js/house.json` and `assets/blender/furniture.json` are historical/bootstrap inputs only. Runtime code must not read them as authoritative state. The furniture JSON remains available to the normalization migration so a fresh installation can be seeded; an existing production database always supplies its stored furniture values during that migration.
