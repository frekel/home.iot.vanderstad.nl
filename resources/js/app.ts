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

// Measured attic geometry. The laundry occupies the complete right side and
// is exactly 5.50 x 2.20 m. The Homey "Zolder" storage room is the top-left
// room and is exactly 2.30 x 2.00 m, kept anchored to the outside corner.
// Lily's usable bedroom width is 4.40 m because a knee wall was installed
// beneath the sloping roof. The roof shell itself remains at its full size.
const attic = house.floors.find((floor) => floor.id === 'attic');
if (attic) {
    const laundry = attic.rooms.find((room) => room.id === 'laundry');
    if (laundry) {
        laundry.polygon = [[1.8, -2.75], [4, -2.75], [4, 2.75], [1.8, 2.75]];
    }

    const bedroom = attic.rooms.find((room) => room.id === 'attic-bedroom');
    if (bedroom) {
        bedroom.polygon = [[-2.6, -2.75], [1.8, -2.75], [1.8, 0.1807], [-2.6, 0.1807]];
    }

    const storage = attic.rooms.find((room) => room.id === 'attic-closet');
    if (storage) {
        storage.polygon = [[-4, 0.75], [-1.7, 0.75], [-1.7, 2.75], [-4, 2.75]];
    }

    // Move the laundry partition to x=1.80, preserving its existing doorway.
    for (const wall of attic.walls) {
        if (Math.abs(wall[0] - 1.8579) < 0.0001 && Math.abs(wall[2] - 1.8579) < 0.0001) {
            wall[0] = 1.8;
            wall[2] = 1.8;
        }
    }

    // Add the knee wall that creates Lily's 4.40 m usable room width.
    const lilyKneeWall = attic.walls.find((wall) =>
        Math.abs(wall[0] + 2.6) < 0.0001 && Math.abs(wall[2] + 2.6) < 0.0001,
    );
    if (!lilyKneeWall) {
        attic.walls.push([-2.6, -2.75, -2.6, 0.1807]);
    }

    // Rebuild the measured Zolder boundary while preserving the existing
    // approximately 1 m doorway in its lower wall.
    const storageWall1 = attic.walls.find((wall) =>
        Math.abs(wall[0] + 4) < 0.0001 && Math.abs(wall[1] - 0.2248) < 0.0001 &&
        Math.abs(wall[2] + 2.8679) < 0.0001 && Math.abs(wall[3] - 0.2248) < 0.0001,
    );
    if (storageWall1) storageWall1.splice(0, 4, -4, 0.75, -2.8679, 0.75);

    const storageWall2 = attic.walls.find((wall) =>
        Math.abs(wall[0] + 1.872) < 0.0001 && Math.abs(wall[1] - 0.2248) < 0.0001 &&
        Math.abs(wall[2] + 1.2002) < 0.0001 && Math.abs(wall[3] - 0.2248) < 0.0001,
    );
    if (storageWall2) storageWall2.splice(0, 4, -1.872, 0.75, -1.7, 0.75);

    const storageSide = attic.walls.find((wall) =>
        Math.abs(wall[0] + 1.2942) < 0.0001 && Math.abs(wall[2] + 1.2942) < 0.0001,
    );
    if (storageSide) storageSide.splice(0, 4, -1.7, 0.75, -1.7, 2.75);

    const hallWall = attic.walls.find((wall) =>
        Math.abs(wall[0] + 0.209) < 0.0001 && Math.abs(wall[1] - 0.2248) < 0.0001 &&
        Math.abs(wall[2] - 1.8579) < 0.0001,
    );
    if (hallWall) hallWall[2] = 1.8;
}

const RootApp = location.pathname === '/measure' ? MeasureApp : App;
createApp(RootApp).mount('#app');
