<?php

use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Add Institution')] class extends Component {
    use WithFileUploads;

    public ?string $name = '';
    public float $default_allowance = 0;
    public string $acronym = '';
    public ?string $address = '';
    public string $email = '';
    public string $phone = '';
    public string $established_year = '';
    public string $status = 'active';
    public $logo;

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized. Only Super Admins can create institutions.');
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'                 => ['required', 'string', 'max:255', 'unique:institutions,name'],
            'default_allowance'    => ['required', 'numeric', 'min:0'],
            'acronym'              => ['nullable', 'string', 'max:20'],
            'address'              => ['nullable', 'string', 'max:500'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'status'           => ['required', 'in:active,inactive'],
            'logo'             => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->logo) {
            $validated['logo_path'] = $this->logo->store('institutions/logos', 'public');
        }

        Institution::create($validated);

        session()->flash('success', 'Institution created successfully.');

        $this->redirect(route('cms.institutions.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl">
            <div class="mb-6">
                <flux:heading size="xl">{{ __('Add Institution') }}</flux:heading>
                <flux:subheading>{{ __('Register a new academic institution') }}</flux:subheading>
            </div>

            <form wire:submit="save" class="space-y-6">
                <flux:fieldset>
                    <flux:legend>{{ __('Institution Details') }}</flux:legend>
 
                    <div class="grid gap-6">
                        <div class="flex items-center gap-6">
                            <div class="relative group">
                                <div class="w-32 h-32 rounded-2xl bg-zinc-100 dark:bg-zinc-800 border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex items-center justify-center overflow-hidden">
                                    @if ($logo)
                                        <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @else
                                        <flux:icon icon="building-library" class="w-8 h-8 text-zinc-400" />
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 space-y-2">
                                <flux:input type="file" wire:model="logo" accept="image/*" :label="__('Choose Logo')" />
                                <flux:description>{{ __('Max 1MB. JPEG, PNG, or WEBP.') }}</flux:description>
                                <flux:error name="logo" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="name" :label="__('Institution Name')" required />
                            <flux:input wire:model="default_allowance" :label="__('Default Attendance Allowance')" type="number" step="0.01" prefix="₦" required />
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="acronym" :label="__('Acronym')" :placeholder="__('e.g. LASU')" />
                            <flux:input wire:model="established_year" :label="__('Established Year')" type="number" :placeholder="__('e.g. 1983')" />
                        </div>

                        <flux:textarea wire:model="address" :label="__('Address')" :placeholder="__('Physical location')" rows="2" />

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="email" :label="__('Email')" type="email" :placeholder="__('official@institution.edu')" />
                            <flux:input wire:model="phone" :label="__('Phone')" :placeholder="__('e.g. +234-800-0000')" />
                        </div>

                        <flux:select wire:model="status" :label="__('Status')">
                            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                            <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                        </flux:select>
                    </div>
                </flux:fieldset>

                <div class="flex items-center justify-end gap-3">
                    <flux:button :href="route('cms.institutions.index')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Save Institution') }}
                    </flux:button>
                </div>
            </form>
        </div>
</div>
