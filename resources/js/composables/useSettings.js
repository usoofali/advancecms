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
            const data = await response.json();
            settings.value = data;

            if (data?.theme && typeof document !== 'undefined') {
                const root = document.documentElement;
                if (data.theme.accent) root.style.setProperty('--color-accent', data.theme.accent);
                if (data.theme.accent_content) root.style.setProperty('--color-accent-content', data.theme.accent_content);
                if (data.theme.accent_foreground) root.style.setProperty('--color-accent-foreground', data.theme.accent_foreground);
            }
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
