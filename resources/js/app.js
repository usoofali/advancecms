import './bootstrap';
import { createApp } from 'vue';
import CbtEngine from './components/CBT/CbtEngine.vue';
import IdCardPrint from './components/IdCards/IdCardPrint.vue';
import PercentageSlider from './components/Invoices/PercentageSlider.vue';

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

window.initPercentSliders = function() {
    document.querySelectorAll('.vue-percent-slider-app').forEach((el) => {
        if (!el.__vue_app_initialized) {
            el.__vue_app_initialized = true;
            const initialValue = parseInt(el.getAttribute('data-value') || '100', 10);
            const label = el.getAttribute('data-label') || '';
            const description = el.getAttribute('data-description') || '';
            const inputName = el.getAttribute('data-name') || '';

            const app = createApp(PercentageSlider, {
                initialValue,
                label,
                description,
                inputName,
            });
            app.mount(el);
        }
    });
};

document.addEventListener('livewire:navigated', () => {
    window.initCbtApp();
    window.initIdCardPrintApp();
    window.initPercentSliders();
});
window.initCbtApp();
window.initIdCardPrintApp();
window.initPercentSliders();

