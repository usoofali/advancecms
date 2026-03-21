<?php

use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Edit Institution')] class extends Component {
    use WithFileUploads;

    public Institution $institution;

    public ?string $name = '';
    public float $default_allowance = 0;
    public ?string $acronym = '';
    public ?string $address = '';
    public ?string $email = '';
    public ?string $phone = '';
    public ?string $established_year = '';
    public string $status = 'active';
    public $logo;

    public function mount(Institution $institution): void
    {
        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized. Only Super Admins can edit institutions.');
        }

        $this->institution = $institution;
        $this->name = $institution->name;
        $this->default_allowance = (float) $institution->default_allowance;
        $this->acronym = $institution->acronym ?? '';
        $this->address = $institution->address ?? '';
        $this->email = $institution->email ?? '';
        $this->phone = $institution->phone ?? '';
        $this->established_year = $institution->established_year ? (string) $institution->established_year : '';
        $this->status = $institution->status;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'                 => ['required', 'string', 'max:255', 'unique:institutions,name,' . $this->institution->id],
            'default_allowance'    => ['required', 'numeric', 'min:0'],
            'acronym'              => ['nullable', 'string', 'max:20'],
            'address'              => ['nullable', 'string'],
            'email'                => ['nullable', 'email', 'max:255'],
            'phone'                => ['nullable', 'string', 'max:30'],
            'established_year'     => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'status'               => ['required', 'in:active,inactive'],
            'logo'                 => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->logo) {
            // Delete old logo if exists
            if ($this->institution->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->institution->logo_path);
            }
            $validated['logo_path'] = $this->logo->store('institutions/logos', 'public');
        }

        $this->institution->update($validated);

        session()->flash('success', 'Institution updated successfully.');

        $this->redirect(route('cms.institutions.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl">
            <div class="mb-6">
                <flux:heading size="xl">{{ __('Edit Institution') }}</flux:heading>
                <flux:subheading>{{ $name }}</flux:subheading>
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
                                    @elseif ($institution->logo_path)
                                        <img src="{{ $institution->logo_url }}" class="w-full h-full object-cover">
                                    @else
                                        <flux:icon icon="building-library" class="w-8 h-8 text-zinc-400" />
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 space-y-2">
                                <flux:input type="file" wire:model="logo" accept="image/*" :label="__('Change Logo')" />
                                <flux:description>{{ __('Max 1MB. JPEG, PNG, or WEBP.') }}</flux:description>
                                <flux:error name="logo" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="name" :label="__('Institution Name')" required />
                            <flux:input wire:model="default_allowance" :label="__('Default Attendance Allowance')" type="number" step="0.01" prefix="₦" required />
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="acronym" :label="__('Acronym')" />
                            <flux:input wire:model="established_year" :label="__('Established Year')" type="number" />
                        </div>

                        <flux:textarea wire:model="address" :label="__('Address')" rows="2" />

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="email" :label="__('Email')" type="email" />
                            <flux:input wire:model="phone" :label="__('Phone')" />
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
                        {{ __('Update Institution') }}
                    </flux:button>
                </div>
            </form>
        </div>
</div>
