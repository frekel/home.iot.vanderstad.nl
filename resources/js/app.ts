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

const RootApp = location.pathname === '/measure' ? MeasureApp : App;
createApp(RootApp).mount('#app');
