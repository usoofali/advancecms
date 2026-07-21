<template>
    <div class="bg-white">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:py-24 lg:px-8">
            <div class="divide-y-2 divide-zinc-200">
                <div class="lg:grid lg:grid-cols-3 lg:gap-8">
                    <h2 class="text-2xl font-extrabold text-zinc-900 sm:text-3xl">Get in touch</h2>
                    <div class="mt-8 grid grid-cols-1 gap-12 sm:grid-cols-2 sm:gap-x-8 sm:gap-y-12 lg:mt-0 lg:col-span-2">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">Email</h3>
                            <dl class="mt-2 text-base text-zinc-500">
                                <div>
                                    <dt class="sr-only">Email</dt>
                                    <dd>
                                        <a :href="`mailto:${settings?.contact_email || 'info@cshtgusau.edu.ng'}`" class="hover:text-accent-content transition-colors">
                                            {{ settings?.contact_email || 'info@cshtgusau.edu.ng' }}
                                        </a>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">Phone</h3>
                            <dl class="mt-2 text-base text-zinc-500">
                                <div>
                                    <dt class="sr-only">Phone number</dt>
                                    <dd>
                                        <a :href="`tel:${settings?.contact_phone || '+234 800 000 0000'}`" class="hover:text-accent-content transition-colors">
                                            {{ settings?.contact_phone || '+234 800 000 0000' }}
                                        </a>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div class="sm:col-span-2">
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">Address</h3>
                            <dl class="mt-2 text-base text-zinc-500">
                                <div>
                                    <dt class="sr-only">Physical Address</dt>
                                    <dd class="whitespace-pre-wrap">{{ settings?.address || 'Community School of Health Technology, Gusau, Zamfara State, Nigeria' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
                
                <div v-if="settings?.social_facebook || settings?.social_twitter || settings?.social_linkedin" class="mt-16 pt-16 lg:grid lg:grid-cols-3 lg:gap-8">
                    <h2 class="text-2xl font-extrabold text-zinc-900 sm:text-3xl">Social Media</h2>
                    <div class="mt-8 grid grid-cols-1 gap-12 sm:grid-cols-2 sm:gap-x-8 sm:gap-y-12 lg:mt-0 lg:col-span-2">
                        <div v-if="settings?.social_facebook">
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">Facebook</h3>
                            <a :href="settings.social_facebook" target="_blank" class="mt-2 text-base text-zinc-500 hover:text-accent-content transition-colors">
                                Follow us on Facebook
                            </a>
                        </div>
                        <div v-if="settings?.social_twitter">
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">Twitter (X)</h3>
                            <a :href="settings.social_twitter" target="_blank" class="mt-2 text-base text-zinc-500 hover:text-accent-content transition-colors">
                                Follow us on Twitter
                            </a>
                        </div>
                        <div v-if="settings?.social_linkedin">
                            <h3 class="text-lg leading-6 font-medium text-zinc-900">LinkedIn</h3>
                            <a :href="settings.social_linkedin" target="_blank" class="mt-2 text-base text-zinc-500 hover:text-accent-content transition-colors">
                                Connect on LinkedIn
                            </a>
                        </div>
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
