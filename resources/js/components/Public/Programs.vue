<template>
    <div class="bg-zinc-50 py-16 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-zinc-900 sm:text-4xl">Academic Programs</h2>
                <p class="mt-4 max-w-2xl mx-auto text-xl text-zinc-500">Discover our range of accredited programs designed for your success.</p>
            </div>

            <div v-if="loading" class="mt-12 text-center text-zinc-500">
                Loading programs...
            </div>
            <div v-else-if="error" class="mt-12 text-center text-red-500">
                Failed to load programs.
            </div>
            <div v-else class="mt-12">
                <div v-if="programs.length === 0" class="text-center text-zinc-500">
                    No programs currently available.
                </div>
                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="program in programs" :key="program.id" class="bg-white overflow-hidden shadow-sm rounded-lg border border-zinc-200 hover:shadow-md transition-shadow">
                        <div class="px-6 py-5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-800 uppercase tracking-wide">
                                    {{ program.type }}
                                </span>
                                <span class="text-sm font-mono text-zinc-400">{{ program.code }}</span>
                            </div>
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">{{ program.name }}</h3>
                            <p class="mt-2 text-sm text-zinc-500">
                                {{ program.department }}
                            </p>
                        </div>
                        <div class="bg-zinc-50 px-6 py-3 flex justify-between items-center border-t border-zinc-100">
                            <span class="text-xs text-zinc-500">{{ program.institution }}</span>
                            <a href="/apply" class="text-sm font-semibold text-accent-content hover:underline">Apply &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const programs = ref([]);
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
    try {
        const response = await fetch('/api/public/programs');
        programs.value = await response.json();
    } catch (e) {
        error.value = e;
        console.error('Failed to load programs', e);
    } finally {
        loading.value = false;
    }
});
</script>
