import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/Public/App.vue';
import Home from './components/Public/Home.vue';
import About from './components/Public/About.vue';
import Programs from './components/Public/Programs.vue';
import Contact from './components/Public/Contact.vue';

const routes = [
    { path: '/', component: Home, name: 'home' },
    { path: '/about', component: About, name: 'about' },
    { path: '/programs', component: Programs, name: 'programs' },
    { path: '/contact', component: Contact, name: 'contact' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

window.initPublicWebsiteApp = function() {
    const appElement = document.getElementById('public-website-app');
    if (appElement && !appElement.__vue_app_initialized) {
        appElement.__vue_app_initialized = true;
        const app = createApp(App);
        app.use(router);
        app.mount('#public-website-app');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.initPublicWebsiteApp();
});
