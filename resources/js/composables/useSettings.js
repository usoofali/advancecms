import { ref } from 'vue';

const settings = ref(null);
const loading = ref(false);
const error = ref(null);

export function useSettings() {
    const fetchSettings = async () => {
        if (settings.value) return; // Return cached
        loading.value = true;
        try {
            const response = await fetch('/api/public/website-settings');
            settings.value = await response.json();
        } catch (e) {
            error.value = e;
            console.error('Failed to load settings', e);
        } finally {
            loading.value = false;
        }
    };

    return {
        settings,
        loading,
        error,
        fetchSettings,
    };
}
