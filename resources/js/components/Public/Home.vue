<template>
    <div>
        <!-- Hero Section -->
        <div class="relative bg-white overflow-hidden border-b border-zinc-200">
            <div class="max-w-7xl mx-auto">
                <div class="relative z-10 pb-8 bg-white sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                    <svg class="hidden lg:block absolute right-0 inset-y-0 h-full w-48 text-white transform translate-x-1/2" fill="currentColor" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <polygon points="50,0 100,0 50,100 0,100" />
                    </svg>

                    <div class="relative pt-10 px-4 sm:pt-16 sm:px-6 lg:px-8 lg:pt-20">
                        <div class="sm:text-center lg:text-left">
                            <h1 class="text-4xl tracking-tight font-extrabold text-zinc-900 sm:text-5xl md:text-6xl">
                                <span class="block xl:inline">{{ settings?.hero_title || 'Welcome to' }}</span>
                                <span class="block text-zinc-600"></span>
                            </h1>
                            <p class="mt-3 text-base text-zinc-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                                {{ settings?.hero_subtitle || 'A modern, comprehensive system for managing your educational institution.' }}
                            </p>
                            <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                                <div class="rounded-md shadow">
                                    <a href="/apply" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 md:py-4 md:text-lg md:px-10 transition-colors">
                                        Apply for Admission
                                    </a>
                                </div>
                                <div class="mt-3 sm:mt-0 sm:ml-3">
                                    <router-link :to="{ name: 'programs' }" class="w-full flex items-center justify-center px-8 py-3 border border-zinc-300 text-base font-medium rounded-md text-zinc-700 bg-white hover:bg-zinc-50 md:py-4 md:text-lg md:px-10 transition-colors">
                                        View Programs
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2 bg-zinc-100 flex items-center justify-center">
                <!-- Fallback illustration or image -->
                <div class="w-full h-64 sm:h-72 md:h-96 lg:h-full bg-zinc-200 flex items-center justify-center text-zinc-400 relative overflow-hidden">
                    <img v-if="settings?.hero_image" :src="settings.hero_image" class="w-full h-full object-cover absolute inset-0" alt="Hero Image">
                    <svg v-else class="w-24 h-24 opacity-50 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="bg-zinc-900">
            <div class="max-w-7xl mx-auto py-12 px-4 sm:py-16 sm:px-6 lg:px-8 lg:py-20">
                <div class="max-w-4xl mx-auto text-center">
                    <h2 class="text-3xl font-extrabold text-white sm:text-4xl">Trusted by thousands of students</h2>
                    <p class="mt-3 text-xl text-zinc-300 sm:mt-4">Our platform manages the entire academic lifecycle seamlessly.</p>
                </div>
                <dl class="mt-10 text-center sm:max-w-3xl sm:mx-auto sm:grid sm:grid-cols-3 sm:gap-8">
                    <div class="flex flex-col">
                        <dt class="order-2 mt-2 text-lg leading-6 font-medium text-zinc-400">Students</dt>
                        <dd class="order-1 text-5xl font-extrabold text-white">{{ stats?.students || '...' }}</dd>
                    </div>
                    <div class="flex flex-col mt-10 sm:mt-0">
                        <dt class="order-2 mt-2 text-lg leading-6 font-medium text-zinc-400">Programs</dt>
                        <dd class="order-1 text-5xl font-extrabold text-white">{{ stats?.programs || '...' }}</dd>
                    </div>
                    <div class="flex flex-col mt-10 sm:mt-0">
                        <dt class="order-2 mt-2 text-lg leading-6 font-medium text-zinc-400">Staff</dt>
                        <dd class="order-1 text-5xl font-extrabold text-white">{{ stats?.staff || '...' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Features grid -->
        <div class="py-16 bg-white overflow-hidden lg:py-24">
            <div class="relative max-w-xl mx-auto px-4 sm:px-6 lg:px-8 lg:max-w-7xl">
                <div class="relative">
                    <h2 class="text-center text-3xl leading-8 font-extrabold tracking-tight text-zinc-900 sm:text-4xl">A better way to learn and manage</h2>
                    <p class="mt-4 max-w-3xl mx-auto text-center text-xl text-zinc-500">Everything you need to manage your academic journey, right from your dashboard.</p>
                </div>

                <div class="relative mt-12 lg:mt-24 lg:grid lg:grid-cols-2 lg:gap-8 lg:items-center">
                    <div class="relative">
                        <h3 class="text-2xl font-extrabold text-zinc-900 tracking-tight sm:text-3xl">Comprehensive Student Portal</h3>
                        <p class="mt-3 text-lg text-zinc-500">Access your courses, results, and invoices all from one unified dashboard designed for simplicity and speed.</p>

                        <dl class="mt-10 space-y-10">
                            <div class="relative">
                                <dt>
                                    <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-zinc-900 text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <p class="ml-16 text-lg leading-6 font-medium text-zinc-900">Industrial Placements (SIWES)</p>
                                </dt>
                                <dd class="mt-2 ml-16 text-base text-zinc-500">Manage your industrial training placements, download letters, and track your progress effortlessly.</dd>
                            </div>

                            <div class="relative">
                                <dt>
                                    <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-zinc-900 text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="ml-16 text-lg leading-6 font-medium text-zinc-900">Automated Results & Transcripts</p>
                                </dt>
                                <dd class="mt-2 ml-16 text-base text-zinc-500">Real-time access to your semester results and an automated transcript generation system.</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-10 -mx-4 relative lg:mt-0" aria-hidden="true">
                        <div class="bg-zinc-100 rounded-xl p-8 shadow-inner border border-zinc-200">
                            <!-- Placeholder for app screenshot -->
                            <div class="aspect-w-4 aspect-h-3 bg-white rounded-lg shadow border border-zinc-200 flex items-center justify-center text-zinc-300">
                                <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSettings } from '../../composables/useSettings';

const { settings, fetchSettings } = useSettings();
const stats = ref(null);

onMounted(async () => {
    fetchSettings();
    
    try {
        const response = await fetch('/api/public/stats');
        stats.value = await response.json();
    } catch (e) {
        console.error('Failed to load stats', e);
    }
});
</script>
