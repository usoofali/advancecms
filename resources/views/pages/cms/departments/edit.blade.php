<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Department')] class extends Component {
    public Department $department;
    public int|string $institution_id = '';
    public int|string|null $hod_id = null;
    public string $name = '';
    public string $faculty = '';
    public string $description = '';
    public string $status = 'active';

    public function mount(Department $department): void
    {
        $user_institution_id = auth()->user()->institution_id;
        if ($user_institution_id && $department->institution_id !== $user_institution_id) {
            abort(403, 'Unauthorized. This department record belongs to another institution.');
        }

        $this->department = $department;
        $this->institution_id = $department->institution_id;
        $this->hod_id = $department->hod_id;
        $this->name = $department->name;
        $this->faculty = $department->faculty ?? '';
        $this->description = $department->description ?? '';
        $this->status = $department->status;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'hod_id'         => ['nullable', 'exists:staff,id'],
            'name'           => ['required', 'string', 'max:255'],
            'faculty'        => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        $this->department->update($validated);

        session()->flash('success', 'Department updated successfully.');

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
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Department') }}</flux:heading>
        <flux:subheading>{{ $department->name }}</flux:subheading>
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

                <flux:input wire:model="name" :label="__('Department Name')" required />

                <flux:select wire:model="hod_id" :label="__('Head of Department')" :placeholder="__('Select HOD...')">
                    <flux:select.option value="null">{{ __('None') }}</flux:select.option>
                    @foreach ($staffMembers as $staff)
                    <flux:select.option :value="$staff->id">{{ $staff->first_name }} {{ $staff->last_name }}
                    </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="faculty" :label="__('Faculty / School')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

                <flux:select wire:model="status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.departments.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update Department') }}</flux:button>
        </div>
    </form>
</div>
</div>