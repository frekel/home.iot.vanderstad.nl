import { createApp } from 'vue';
import App from './App.vue';
import MeasureApp from './MeasureApp.vue';
import house from './house.json';
import '../css/app.css';
import '../css/measure.css';

// User-measured correction: opposite the first-floor stairs the wall between
// the two bedroom doors is 63 cm wide. Keep the original centre point and
// widen only that middle wall segment.
const upper = house.floors.find((floor) => floor.id === 'upper');
const middleWall = upper?.walls.find((wall) =>
    Math.abs(wall[0] + 0.2326) < 0.0001 &&
    Math.abs(wall[1] - 0.2292) < 0.0001 &&
    Math.abs(wall[2] - 0.1628) < 0.0001 &&
    Math.abs(wall[3] - 0.2292) < 0.0001,
);
if (middleWall) {
    middleWall[0] = -0.3499;
    middleWall[2] = 0.2801;
}

// Final measured attic geometry inside an 8.00 x 5.50 m roof shell.
// Along the 8 m axis: 0.60 knee-wall void + 4.40 rooms + 0.20 wall
// + 2.20 laundry + 0.60 knee-wall void.
// Across the 5.50 m axis: Lily 3.30 + 0.20 wall + Zolder/closet 2.00.
// In the upper 4.40 m block: closet 1.90 + 0.20 wall + Zolder 2.30.
const attic = house.floors.find((floor) => floor.id === 'attic');
if (attic) {
    const bedroom = attic.rooms.find((room) => room.id === 'attic-bedroom');
    if (bedroom) {
        bedroom.polygon = [[-3.4, -2.75], [1.0, -2.75], [1.0, 0.55], [-3.4, 0.55]];
    }

    const storage = attic.rooms.find((room) => room.id === 'attic-closet');
    if (storage) {
        storage.polygon = [[-3.4, 0.75], [-1.5, 0.75], [-1.5, 2.75], [-3.4, 2.75]];
    }

    const zolder = attic.rooms.find((room) => room.id === 'attic-hall');
    if (zolder) {
        zolder.polygon = [[-1.3, 0.75], [1.0, 0.75], [1.0, 2.75], [-1.3, 2.75]];
    }

    // The stair opening lives inside the 2.30 x 2.00 m Zolder room.
    const stairs = attic.rooms.find((room) => room.id === 'attic-stairs');
    if (stairs) {
        stairs.polygon = [[-1.3, 1.70], [1.0, 1.70], [1.0, 2.75], [-1.3, 2.75]];
    }

    const laundry = attic.rooms.find((room) => room.id === 'laundry');
    if (laundry) {
        laundry.polygon = [[1.2, -2.75], [3.4, -2.75], [3.4, 2.75], [1.2, 2.75]];
    }

    // Rebuild all attic walls from the measured geometry. The outer roof shell
    // remains 8.00 x 5.50 m. Interior room walls occupy the 20 cm gaps between
    // the clear room dimensions. Door openings are retained for both upper
    // rooms and between Zolder and Washok.
    attic.walls.splice(0, attic.walls.length,
        [-4, -2.75, 4, -2.75],
        [-4, -2.75, -4, 2.75],
        [-4, 2.75, 4, 2.75],
        [4, -2.75, 4, 2.75],

        // Knee walls are centred 5 cm outside the usable area, so their inner
        // faces are exactly at x=-3.40 and x=3.40.
        [-3.45, -2.75, -3.45, 2.75],
        [3.45, -2.75, 3.45, 2.75],

        // 20 cm wall between the 4.40 m room block and the Washok.
        // Keep a 90 cm doorway inside the Zolder section.
        [1.1, -2.75, 1.1, 0.90],
        [1.1, 1.80, 1.1, 2.75],

        // 20 cm wall between Lily and Kledingkast/Zolder. The Lily-to-Zolder
        // door starts 10 cm from the Kledingkast/Zolder partition and is 80 cm wide.
        [-3.4, 0.65, -2.85, 0.65],
        [-2.05, 0.65, -1.5, 0.65],
        [-1.3, 0.65, -1.2, 0.65],
        [-0.4, 0.65, 1.0, 0.65],

        // 20 cm wall between Kledingkast and Zolder.
        [-1.4, 0.75, -1.4, 2.75],
    );
}

const RootApp = location.pathname === '/measure' ? MeasureApp : App;
createApp(RootApp).mount('#app');