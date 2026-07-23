<template>
    <div class="min-h-screen flex flex-col bg-zinc-50 text-zinc-900 selection:bg-accent selection:text-accent-foreground relative">
        <!-- Top Navigation Loading Bar -->
        <div v-if="isNavigating" class="fixed top-0 left-0 right-0 z-[60] h-1 bg-accent/20 overflow-hidden">
            <div class="h-full bg-accent animate-pulse-fast w-full"></div>
        </div>

        <!-- Initial Full-Page Logo Loading Screen -->
        <Transition name="splash-fade">
            <div v-if="!isReady" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-950">
                <!-- Background Ambient Decorative Gradients -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>

                <div class="relative flex flex-col items-center p-8 sm:p-10 rounded-3xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border border-zinc-200/80 dark:border-zinc-800 shadow-2xl max-w-sm w-full mx-4 space-y-6">
                    <!-- Brand Logo / Emblem -->
                    <div class="relative flex items-center justify-center">
                        <div class="absolute -inset-3 bg-accent/30 rounded-2xl blur-lg animate-pulse"></div>
                        <div v-if="settings?.system_logo" class="relative z-10 p-3 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm">
                            <img :src="settings.system_logo" alt="System Logo" class="h-16 w-auto object-contain animate-pulse">
                        </div>
                        <div v-else class="relative z-10 w-16 h-16 rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 flex items-center justify-center font-bold text-3xl shadow-lg">
                            {{ settings?.website_name ? settings.website_name.charAt(0) : 'A' }}
                        </div>
                    </div>

                    <!-- Brand Name & Status Text -->
                    <div class="text-center space-y-1">
                        <h2 class="font-bold text-xl text-zinc-900 dark:text-white tracking-tight">
                            {{ settings?.website_name || 'Loading...' }}
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium tracking-wide">
                            Loading website...
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-44 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden relative">
                        <div class="h-full bg-accent rounded-full animate-progress-slide"></div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Navigation -->
        <nav class="bg-white/95 backdrop-blur-md border-b border-zinc-200 sticky top-0 z-50 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                            <router-link :to="{ name: 'home' }" class="flex items-center gap-2">
                                <img v-if="settings?.system_logo" :src="settings.system_logo" alt="System Logo" class="h-10 w-auto">
                                <div v-else class="w-8 h-8 bg-zinc-900 text-white flex items-center justify-center rounded-lg font-bold text-lg">
                                    {{ settings?.website_name ? settings.website_name.charAt(0) : 'A' }}
                                </div>
                                <span class="font-bold text-xl tracking-tight">{{ settings?.website_name }}</span>
                            </router-link>
                        </div>
                        <!-- Desktop Menu -->
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-8">
                            <router-link :to="{ name: 'home' }" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300" active-class="border-accent text-accent-content font-semibold" exact-active-class="border-accent text-accent-content font-semibold">Home</router-link>
                            <router-link :to="{ name: 'about' }" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300" active-class="border-accent text-accent-content font-semibold">About</router-link>
                            <router-link :to="{ name: 'programs' }" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300" active-class="border-accent text-accent-content font-semibold">Programs</router-link>
                            <router-link :to="{ name: 'contact' }" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300" active-class="border-accent text-accent-content font-semibold">Contact</router-link>
                        </div>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:items-center space-x-4">
                        <a href="/login" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 transition-colors">Login</a>
                        <a href="/apply" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-accent-foreground bg-accent hover:opacity-90 transition-colors">Apply Now</a>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <div class="flex items-center sm:hidden">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-zinc-400 hover:text-zinc-500 hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-accent" aria-controls="mobile-menu" aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <svg v-if="!mobileMenuOpen" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg v-else class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div v-show="mobileMenuOpen" class="sm:hidden" id="mobile-menu">
                <div class="pt-2 pb-3 space-y-1">
                    <router-link :to="{ name: 'home' }" @click="mobileMenuOpen = false" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium border-transparent text-zinc-600 hover:bg-zinc-50 hover:border-zinc-300 hover:text-zinc-800" active-class="bg-accent/10 border-accent text-accent-content font-semibold" exact-active-class="bg-accent/10 border-accent text-accent-content font-semibold">Home</router-link>
                    <router-link :to="{ name: 'about' }" @click="mobileMenuOpen = false" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium border-transparent text-zinc-600 hover:bg-zinc-50 hover:border-zinc-300 hover:text-zinc-800" active-class="bg-accent/10 border-accent text-accent-content font-semibold">About</router-link>
                    <router-link :to="{ name: 'programs' }" @click="mobileMenuOpen = false" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium border-transparent text-zinc-600 hover:bg-zinc-50 hover:border-zinc-300 hover:text-zinc-800" active-class="bg-accent/10 border-accent text-accent-content font-semibold">Programs</router-link>
                    <router-link :to="{ name: 'contact' }" @click="mobileMenuOpen = false" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium border-transparent text-zinc-600 hover:bg-zinc-50 hover:border-zinc-300 hover:text-zinc-800" active-class="bg-accent/10 border-accent text-accent-content font-semibold">Contact</router-link>
                </div>
                <div class="pt-4 pb-3 border-t border-zinc-200">
                    <div class="flex flex-col px-4 space-y-3">
                        <a href="/login" class="block text-center w-full px-4 py-2 border border-zinc-300 rounded-md shadow-sm text-base font-medium text-zinc-700 bg-white hover:bg-zinc-50">Login</a>
                        <a href="/apply" class="block text-center w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-accent-foreground bg-accent hover:opacity-90 transition-colors">Apply Now</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content (Router View) -->
        <main class="flex-grow">
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-zinc-200" aria-labelledby="footer-heading">
            <h2 id="footer-heading" class="sr-only">Footer</h2>
            <div class="max-w-7xl mx-auto pt-16 pb-8 px-4 sm:px-6 lg:pt-24 lg:px-8">
                <div class="xl:grid xl:grid-cols-3 xl:gap-8">
                    <div class="space-y-8 xl:col-span-1">
                        <router-link :to="{ name: 'home' }" class="flex items-center gap-2">
                            <img v-if="settings?.system_logo" :src="settings.system_logo" alt="System Logo" class="h-10 w-auto grayscale opacity-80 hover:grayscale-0 hover:opacity-100 transition-all">
                            <div v-else class="w-10 h-10 bg-accent text-accent-foreground flex items-center justify-center rounded-lg font-bold text-2xl">
                                {{ settings?.website_name ? settings.website_name.charAt(0) : 'A' }}
                            </div>
                            <span class="font-bold text-2xl tracking-tight text-zinc-900">{{ settings?.website_name }}</span>
                        </router-link>
                        <p class="text-base text-zinc-500 leading-relaxed max-w-xs">
                            {{ settings?.hero_subtitle}}
                        </p>
                        <div class="flex space-x-6">
                            <a v-if="settings?.social_facebook" :href="settings.social_facebook" class="text-zinc-400 hover:text-accent-content transition-colors">
                                <span class="sr-only">Facebook</span>
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
                            </a>
                            <a v-if="settings?.social_twitter" :href="settings.social_twitter" class="text-zinc-400 hover:text-accent-content transition-colors">
                                <span class="sr-only">Twitter</span>
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" /></svg>
                            </a>
                            <a v-if="settings?.social_linkedin" :href="settings.social_linkedin" class="text-zinc-400 hover:text-accent-content transition-colors">
                                <span class="sr-only">LinkedIn</span>
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" clip-rule="evenodd" /></svg>
                            </a>
                        </div>
                    </div>
                    <div class="mt-12 grid grid-cols-2 gap-8 xl:mt-0 xl:col-span-2">
                        <div class="md:grid md:grid-cols-2 md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 tracking-wider uppercase">Navigation</h3>
                                <ul role="list" class="mt-4 space-y-4">
                                    <li><router-link :to="{ name: 'home' }" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Home</router-link></li>
                                    <li><router-link :to="{ name: 'about' }" class="text-base text-zinc-500 hover:text-accent-content transition-colors">About Us</router-link></li>
                                    <li><router-link :to="{ name: 'programs' }" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Programs</router-link></li>
                                    <li><router-link :to="{ name: 'contact' }" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Contact</router-link></li>
                                </ul>
                            </div>
                            <div class="mt-12 md:mt-0">
                                <h3 class="text-sm font-semibold text-zinc-900 tracking-wider uppercase">Portals</h3>
                                <ul role="list" class="mt-4 space-y-4">
                                    <li><a href="/login" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Student Login</a></li>
                                    <li><a href="/login" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Staff Portal</a></li>
                                    <li><a href="/apply" class="text-base text-zinc-500 hover:text-accent-content transition-colors">Apply Now</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="md:grid md:grid-cols-1 md:gap-8">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 tracking-wider uppercase">Contact Us</h3>
                                <ul role="list" class="mt-4 space-y-4">
                                    <li class="flex items-start">
                                        <svg class="shrink-0 h-6 w-6 text-accent-content" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="ml-3 flex-1 min-w-0 break-words text-base text-zinc-500">{{ settings?.address }}</span>
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="shrink-0 h-6 w-6 text-accent-content" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <a :href="`mailto:${settings?.contact_email}`" class="ml-3 flex-1 min-w-0 break-words text-base text-zinc-500 hover:text-accent-content transition-colors">
                                            {{ settings?.contact_email }}
                                        </a>
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="shrink-0 h-6 w-6 text-accent-content" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <a :href="`tel:${settings?.contact_phone }`" class="ml-3 flex-1 min-w-0 break-words text-base text-zinc-500 hover:text-accent-content transition-colors">
                                            {{ settings?.contact_phone }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-12 border-t border-zinc-200 pt-8 flex flex-col md:flex-row md:items-center md:justify-between">
                    <p class="text-base text-zinc-400 xl:text-center">
                        &copy; {{ new Date().getFullYear() }} {{ settings?.website_name }}. All rights reserved.
                    </p>
                    <div class="mt-4 md:mt-0 text-sm text-zinc-400">
                        Powered by <a href="#" class="text-zinc-500 hover:text-accent-content font-medium transition-colors">YUM IT SOLUTIONS</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import { useSettings } from '../../composables/useSettings';

const mobileMenuOpen = ref(false);
const isNavigating = inject('isNavigating', ref(false));
const { settings, isReady, fetchSettings } = useSettings();

onMounted(() => {
    fetchSettings();
});
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.splash-fade-enter-active,
.splash-fade-leave-active {
  transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.splash-fade-enter-from,
.splash-fade-leave-to {
  opacity: 0;
  transform: scale(0.97);
}

@keyframes progressSlide {
  0% {
    width: 0%;
    margin-left: 0%;
  }
  50% {
    width: 70%;
    margin-left: 15%;
  }
  100% {
    width: 100%;
    margin-left: 0%;
  }
}

.animate-progress-slide {
  animation: progressSlide 1.2s infinite ease-in-out;
}

@keyframes pulseFast {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.animate-pulse-fast {
  animation: pulseFast 0.8s infinite ease-in-out;
}
</style>

