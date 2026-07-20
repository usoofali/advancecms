import './bootstrap';
import { createApp } from 'vue';
import CbtEngine from './components/CBT/CbtEngine.vue';
import IdCardPrint from './components/IdCards/IdCardPrint.vue';

window.initCbtApp = function() {
    const appElement = document.getElementById('cbt-app');
    if (appElement && !appElement.__vue_app_initialized) {
        appElement.__vue_app_initialized = true;
        const payloadStr = appElement.getAttribute('data-payload');
        const app = createApp(CbtEngine, { payloadStr });
        app.mount('#cbt-app');
    }
};

window.initIdCardPrintApp = function() {
    const appElement = document.getElementById('id-card-print-app');
    if (appElement && !appElement.__vue_app_initialized) {
        appElement.__vue_app_initialized = true;
        const payloadStr = appElement.getAttribute('data-payload');
        const app = createApp(IdCardPrint, { payloadStr });
        app.mount('#id-card-print-app');
    }
};

document.addEventListener('livewire:navigated', () => {
    window.initCbtApp();
    window.initIdCardPrintApp();
});
window.initCbtApp();
window.initIdCardPrintApp();
