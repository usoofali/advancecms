import './bootstrap';
import { createApp } from 'vue';
import CbtEngine from './components/CBT/CbtEngine.vue';

window.initCbtApp = function() {
    const appElement = document.getElementById('cbt-app');
    if (appElement && !appElement.__vue_app_initialized) {
        appElement.__vue_app_initialized = true;
        const payloadStr = appElement.getAttribute('data-payload');
        const app = createApp(CbtEngine, { payloadStr });
        app.mount('#cbt-app');
    }
};

document.addEventListener('livewire:navigated', window.initCbtApp);
window.initCbtApp();
