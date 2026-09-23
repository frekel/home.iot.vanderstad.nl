import { createApp } from 'vue';
import App from './App.vue';
import MeasureApp from './MeasureApp.vue';
import '../css/app.css';
import '../css/measure.css';

const RootApp = location.pathname === '/measure' ? MeasureApp : App;
createApp(RootApp).mount('#app');
