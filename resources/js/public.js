import { createApp, ref } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/Public/App.vue';
import Home from './components/Public/Home.vue';
import About from './components/Public/About.vue';
import Programs from './components/Public/Programs.vue';
import Contact from './components/Public/Contact.vue';

const isNavigating = ref(false);

const routes = [
    { path: '/', component: Home, name: 'home' },
    { path: '/about', component: About, name: 'about' },
    { path: '/programs', component: Programs, name: 'programs' },
    { path: '/contact', component: Contact, name: 'contact' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { top: 0 };
        }
    },
});

router.beforeEach((to, from, next) => {
    if (from.name) {
        isNavigating.value = true;
    }
    next();
});

router.afterEach(() => {
    setTimeout(() => {
        isNavigating.value = false;
    }, 200);
});

window.initPublicWebsiteApp = function() {
    const appElement = document.getElementById('public-website-app');
    if (appElement && !appElement.__vue_app_initialized) {
        appElement.__vue_app_initialized = true;
        const app = createApp(App);
        app.provide('isNavigating', isNavigating);
        app.use(router);
        app.mount('#public-website-app');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.initPublicWebsiteApp();
});

