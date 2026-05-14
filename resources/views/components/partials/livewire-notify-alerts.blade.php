<div
    x-data="{
        alerts: [],
        addAlert(data) {
            const id = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
            const allowed = ['success', 'error', 'warning', 'info'];
            
            // Handle array payload or object payload
            const payload = Array.isArray(data) ? (data[0] || {}) : (data || {});
            
            // Determine variant and message
            let variant = 'success';
            let message = '';

            if (typeof payload === 'string') {
                message = payload;
            } else {
                variant = allowed.includes(payload.variant) ? payload.variant : (allowed.includes(payload.type) ? payload.type : 'success');
                message = payload.message || '';
            }

            const timeout = 5000;

            if (message) {
                this.alerts.push({ id, variant, message, timeout });

                if (timeout > 0) {
                    setTimeout(() => this.dismiss(id), timeout + 100);
                }
            }
        },
        dismiss(id) {
            this.alerts = this.alerts.filter(alert => alert.id !== id);
        }
    }"
    x-on:notify.window="addAlert($event.detail)"
    class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-3 px-4 sm:px-0"
>
    <template x-for="alert in alerts" :key="alert.id">
        <div class="pointer-events-auto">
            <template x-if="alert.variant === 'success'">
                <x-alert variant="success" :timeout="0" class="mb-0">
                    <span x-text="alert.message"></span>
                </x-alert>
            </template>

            <template x-if="alert.variant === 'error'">
                <x-alert variant="error" :timeout="0" class="mb-0">
                    <span x-text="alert.message"></span>
                </x-alert>
            </template>

            <template x-if="alert.variant === 'warning'">
                <x-alert variant="warning" :timeout="0" class="mb-0">
                    <span x-text="alert.message"></span>
                </x-alert>
            </template>

            <template x-if="alert.variant === 'info'">
                <x-alert variant="info" :timeout="0" class="mb-0">
                    <span x-text="alert.message"></span>
                </x-alert>
            </template>
        </div>
    </template>
</div>

