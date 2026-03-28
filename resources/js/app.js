import './bootstrap';

window.whTooltips = { colorLinks: true, iconizeLinks: true, renameLinks: false, locale: 'fr' };
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './components/App.vue';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.mount('#app');
