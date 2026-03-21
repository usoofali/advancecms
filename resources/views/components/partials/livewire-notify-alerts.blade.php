<div
    x-data="{
        alerts: [],
        addAlert(data) {
            const id = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
            const allowed = ['success', 'error', 'warning', 'info'];
            
            // Handle array payload or object payload
            const payload = Array.isArray(data) ? (data[0] || {}) : (data || {});
            const variant = allowed.includes(payload.variant) ? payload.variant : (allowed.includes(payload.type) ? payload.type : 'success');
            const message = payload.message || '';
            const timeout = 5000;

            this.alerts.push({ id, variant, message, timeout });

            if (timeout > 0) {
                setTimeout(() => this.dismiss(id), timeout + 100);
            }
        },
        dismiss(id) {
            this.alerts = this.alerts.filter(alert => alert.id !== id);
        },
        init() {
            // Listen for custom notify:show event from global listener
            window.addEventListener('notify:show', (event) => {
                this.addAlert(event.detail);
            });
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

@push('scripts')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('notify', (event) => {
                // When dispatching with array: $this->dispatch('notify', ['type' => 'success', 'message' => '...'])
                // The array is passed as the event itself or in event[0]
                let data = {};
                
                if (Array.isArray(event)) {
                    // If event is an array, get first element
                    data = event[0] || {};
                } else if (event && typeof event === 'object') {
                    // If event is an object, use it directly or check for detail property
                    data = event.detail || event;
                }
                
                const type = data.variant || data.type || 'success';
                const message = data.message || '';
                
                // Dispatch custom event for Alpine to catch
                window.dispatchEvent(new CustomEvent('notify:show', {
                    detail: { variant: type, message }
                }));
            });
        });
    </script>
@endpush

