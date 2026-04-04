<?php

use App\Models\Department;
use App\Models\Program;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Add Program')] class extends Component {
    public int|string|null $institution_id = '';
    public int|string|null $department_id = '';
    public string $name = '';
    public string $acronym = '';
    public int $duration_years = 3;
    public string $award_type = 'diploma';
    public string $status = 'active';

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'department_id'  => ['required', 'exists:departments,id'],
            'name'           => ['required', 'string', 'max:255'],
            'acronym'        => [
                'required',
                'string',
                'max:10',
                Rule::unique('programs', 'acronym')->where(
                    fn ($query) => $query->where('institution_id', $this->institution_id),
                ),
            ],
            'duration_years' => ['required', 'integer', 'min:1', 'max:10'],
            'award_type'    => ['required', 'in:certificate,diploma,degree'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        Program::create($validated);

        session()->flash('success', 'Program created successfully.');

        $this->redirect(route('cms.programs.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'institutions' => auth()->user()->institution_id 
                ? [] 
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'departments' => Department::query()
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Add Program') }}</flux:heading>
        <flux:subheading>{{ __('Define a new academic program') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:fieldset>
            <flux:legend>{{ __('Program Details') }}</flux:legend>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <flux:select wire:model="department_id" :label="__('Department')" required>
                    <flux:select.option value="null">{{ __('Select department...') }}</flux:select.option>
                    @foreach ($departments as $dept)
                    <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" :label="__('Program Name')"
                    :placeholder="__('e.g. B.Sc. Computer Science')" required />

                <flux:input wire:model="acronym" :label="__('Acronym')" :placeholder="__('e.g. COMP')" required />

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="duration_years" :label="__('Duration (Years)')" type="number" required />
                    <flux:select wire:model="award_type" :label="__('Award Type')">
                        <flux:select.option value="certificate">{{ __('Certificate') }}</flux:select.option>
                        <flux:select.option value="diploma">{{ __('Diploma') }}</flux:select.option>
                        <flux:select.option value="degree">{{ __('Degree') }}</flux:select.option>
                    </flux:select>
                </div>

                <flux:select wire:model="status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.programs.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save Program') }}</flux:button>
        </div>
    </form>
</div>
</div>