<template>
    <div class="bg-white py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-zinc-600 font-semibold tracking-wide uppercase">About Us</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-zinc-900 sm:text-4xl">
                    Our Institution
                </p>
            </div>

            <div class="mt-10 lg:mt-20">
                <div class="prose prose-zinc prose-lg mx-auto text-zinc-500">
                    <p v-if="settings?.about_text" class="whitespace-pre-wrap">{{ settings.about_text }}</p>
                    <p v-else>
                        Advance CMS provides a unified platform to manage student data, admissions, placements, and academic records. We are dedicated to providing the best learning environment and administrative experience for both our students and staff.
                    </p>
                    
                    <div v-if="settings?.vision" class="mt-12">
                        <h3 class="text-2xl font-bold text-zinc-900 mb-4">Our Vision</h3>
                        <p class="whitespace-pre-wrap">{{ settings.vision }}</p>
                    </div>
                    
                    <div v-if="settings?.mission" class="mt-12">
                        <h3 class="text-2xl font-bold text-zinc-900 mb-4">Our Mission</h3>
                        <p class="whitespace-pre-wrap">{{ settings.mission }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSettings } from '../../composables/useSettings';

const { settings, fetchSettings } = useSettings();

onMounted(() => {
    fetchSettings();
});
</script>
