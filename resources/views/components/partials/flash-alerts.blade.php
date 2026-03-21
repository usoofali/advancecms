<!-- Flash Messages -->

@if (session()->has('error'))
    <div class="fixed bottom-4 right-4 z-50">
        <x-alert variant="error" :timeout="5000">
            {{ session('error') }}
        </x-alert>
    </div>
@endif

@if (session()->has('success'))
    <div class="fixed bottom-4 right-4 z-50">
        <x-alert variant="success" :timeout="5000">
            {{ session('success') }}
        </x-alert>
    </div>
@endif

@if (session()->has('notify'))
    @php
        $notify = session('notify');
        $variant = in_array($notify['type'] ?? 'success', ['success', 'error', 'warning', 'info']) ? $notify['type'] : 'success';
        $message = $notify['message'] ?? '';
    @endphp
    <div class="fixed bottom-4 right-4 z-50">
        <x-alert :variant="$variant" :timeout="5000">
            {{ $message }}
        </x-alert>
    </div>
@endif


