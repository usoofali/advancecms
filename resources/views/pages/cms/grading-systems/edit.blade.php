<?php

use App\Models\GradingSystem;
use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Grading System')] class extends Component {
    public GradingSystem $gradingSystem;
    public string $name = '';
    public int|string|null $institution_id = null;
    public array $scale = [];

    public function mount(GradingSystem $gradingSystem): void
    {
        Gate::authorize('grading_systems.edit');

        $user_institution_id = auth()->user()->institution_id;
        if ($user_institution_id && $gradingSystem->institution_id !== $user_institution_id && $gradingSystem->institution_id !== null) {
             // Allow editing if it's their institution or global (if permitted)
             // However, strictly speaking, only Super Admins should edit global systems.
             if (!$user_institution_id && $gradingSystem->institution_id === null) {
                 // OK
             } elseif ($user_institution_id && $gradingSystem->institution_id === null) {
                 abort(403, 'Unauthorized. Only Super Admins can edit global grading systems.');
             }
        }

        $this->gradingSystem = $gradingSystem;
        $this->name = $gradingSystem->name;
        $this->institution_id = $gradingSystem->institution_id;
        $this->scale = $gradingSystem->scale;
    }

    public function addRow(): void
    {
        $this->scale[] = ['min' => 0, 'grade' => '', 'point' => 0.0];
    }

    public function removeRow(int $index): void
    {
        unset($this->scale[$index]);
        $this->scale = array_values($this->scale);
    }

    public function save(): void
    {
        Gate::authorize('grading_systems.edit');

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'scale' => ['required', 'array', 'min:1'],
            'scale.*.min' => ['required', 'numeric', 'min:0', 'max:100'],
            'scale.*.grade' => ['required', 'string', 'max:5'],
            'scale.*.point' => ['required', 'numeric', 'min:0'],
        ]);

        // Sort scale by min descending to ensure GradingService logic works
        usort($this->scale, fn($a, $b) => $b['min'] <=> $a['min']);

        $this->gradingSystem->update([
            'name' => $this->name,
            'institution_id' => $this->institution_id ?: null,
            'scale' => $this->scale,
        ]);

        session()->flash('success', 'Grading system updated successfully.');

        $this->redirect(route('cms.grading-systems.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Grading System') }}</flux:heading>
        <flux:subheading>{{ $gradingSystem->name }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model="institution_id" :label="__('Institution')">
                    <flux:select.option value="null">{{ __('Global / System Default') }}</flux:select.option>
                    @foreach (Institution::query()->where('status', 'active')->orderBy('name')->get() as $institution)
                    <flux:select.option :value="$institution->id">{{ $institution->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

                <flux:input wire:model="name" :label="__('System Name')" required />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="md">{{ __('Grading Scale') }}</flux:heading>
                <flux:button icon="plus" size="sm" wire:click="addRow">{{ __('Add Level') }}</flux:button>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-12 gap-4 px-2 text-xs font-bold uppercase text-zinc-500">
                    <div class="col-span-4">{{ __('Min Score (%)') }}</div>
                    <div class="col-span-3">{{ __('Grade') }}</div>
                    <div class="col-span-3">{{ __('Points') }}</div>
                    <div class="col-span-2"></div>
                </div>

                @foreach ($scale as $index => $row)
                <div class="grid grid-cols-12 gap-4 items-center" wire:key="row-{{ $index }}">
                    <div class="col-span-4">
                        <flux:input type="number" wire:model="scale.{{ $index }}.min" min="0" max="100" required />
                    </div>
                    <div class="col-span-3">
                        <flux:input wire:model="scale.{{ $index }}.grade" :placeholder="__('A')" required />
                    </div>
                    <div class="col-span-3">
                        <flux:input type="number" step="0.1" wire:model="scale.{{ $index }}.point" min="0" required />
                    </div>
                    <div class="col-span-2 flex justify-end">
                        <flux:button icon="trash" variant="ghost" size="sm" wire:click="removeRow({{ $index }})" />
                    </div>
                </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.grading-systems.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update System') }}</flux:button>
        </div>
    </form>
</div>
