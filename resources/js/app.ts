import { createApp } from 'vue';
import App from './App.vue';
import MeasureApp from './MeasureApp.vue';
import { refreshHouse } from './house';
import '../css/app.css';
import '../css/dashboard-layout.css';
import '../css/measure.css';

const RootApp = location.pathname === '/measure' ? MeasureApp : App;

refreshHouse()
    .then(() => createApp(RootApp).mount('#app'))
    .catch(() => {
        const host = document.querySelector('#app');
        if (host) {
            host.textContent = 'De woninggeometrie kon niet uit de database worden geladen.';
        }
    });
