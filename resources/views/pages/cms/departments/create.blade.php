<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\Role;
use App\Models\GradingSystem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Add Department')] class extends Component {
    public int|string $institution_id = '';
    public int|string|null $hod_id = null;
    public string $name = '';
    public string $faculty = '';
    public string $description = '';
    public string $status = 'active';
    public int|string|null $grading_system_id = null;
    public int $max_session_units = 24;

    public function mount(): void
    {
        Gate::authorize('departments.create');

        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function save(): void
    {
        Gate::authorize('departments.create');

        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'hod_id'         => ['nullable', 'exists:staff,id'],
            'name'           => ['required', 'string', 'max:255'],
            'faculty'        => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'status'         => ['required', 'in:active,inactive'],
            'grading_system_id' => ['nullable', 'exists:grading_systems,id'],
            'max_session_units' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        Department::create($validated);

        session()->flash('success', 'Department created successfully.');

        $this->redirect(route('cms.departments.index'), navigate: true);
    }
    public function with(): array
    {
        return [
            'staffMembers' => Staff::query()
                ->where('role_id', Role::where('role_name', 'Head of Department (HOD)')->value('role_id'))
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->orderBy('first_name')
                ->get(),
            'gradingSystems' => GradingSystem::query()
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id)->orWhereNull('institution_id'))
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Add Department') }}</flux:heading>
        <flux:subheading>{{ __('Create a new academic department') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:fieldset>
            <flux:legend>{{ __('Department Details') }}</flux:legend>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach (Institution::query()->where('status', 'active')->orderBy('name')->get() as $institution)
                    <flux:select.option :value="$institution->id">{{ $institution->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <flux:input wire:model="name" :label="__('Department Name')"
                    :placeholder="__('e.g. Community Health Department')" required />

                <flux:select wire:model="hod_id" :label="__('Head of Department')" :placeholder="__('Select HOD...')">
                    <flux:select.option value="null">{{ __('None') }}</flux:select.option>
                    @foreach ($staffMembers as $staff)
                    <flux:select.option :value="$staff->id">{{ $staff->first_name }} {{ $staff->last_name }}
                    </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="faculty" :label="__('Faculty / School')"
                    :placeholder="__('e.g. School of Health Technology')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

                <flux:select wire:model="status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="grading_system_id" :label="__('Grading System')" :placeholder="__('Select Grading System...')">
                    <flux:select.option value="null">{{ __('Default System') }}</flux:select.option>
                    @foreach ($gradingSystems as $system)
                    <flux:select.option :value="$system->id">{{ $system->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="max_session_units" :label="__('Max Session Credit Units')"
                    :description="__('The maximum total credit units a student can register for in an entire academic session.')" />
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.departments.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save Department') }}</flux:button>
        </div>
    </form>
</div>
</div>