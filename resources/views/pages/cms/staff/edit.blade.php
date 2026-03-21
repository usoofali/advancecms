<?php

use App\Models\Staff;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Edit Staff')] class extends Component {
    public Staff $staff;
    
    public int|string|null $institution_id = '';
    public int|string|null $role_id = '';
    public ?string $staff_number = '';
    public ?string $first_name = '';
    public ?string $last_name = '';
    public ?string $email = '';
    public ?string $phone = '';
    public ?string $designation = '';
    public ?string $status = 'active';

    public function mount(Staff $staff): void
    {
        $user_institution_id = auth()->user()->institution_id;
        if ($user_institution_id && $staff->institution_id !== $user_institution_id) {
            abort(403, 'Unauthorized. This staff record belongs to another institution.');
        }

        $this->staff = $staff;
        $this->institution_id = $staff->institution_id;
        $this->role_id = $staff->role_id;
        $this->staff_number = $staff->staff_number;
        $this->first_name = $staff->first_name;
        $this->last_name = $staff->last_name;
        $this->email = $staff->email;
        $this->phone = $staff->phone ?? '';
        $this->designation = $staff->designation;
        $this->status = $staff->status;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'institution_id' => ['required', 'exists:institutions,id'],
            'role_id'        => ['required', 'exists:roles,role_id'],
            'staff_number'   => ['required', 'string', 'max:30', 'unique:staff,staff_number,' . $this->staff->id],
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:staff,email,' . $this->staff->id],
            'phone'          => ['nullable', 'string', 'max:30'],
            'designation'    => ['required', 'string', 'max:255'],
            'status'         => ['required', 'in:active,inactive,suspended,retired'],
        ]);

        $this->staff->update($validated);

        session()->flash('success', 'Staff record updated successfully.');

        $this->redirect(route('cms.staff.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'institutions' => auth()->user()->institution_id 
                ? [] 
                : Institution::query()->where('status', 'active')->get(),
            'roles' => Role::query()
                ->whereNotIn('role_id', [1, 9])
                ->orderBy('role_name')
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Staff Member') }}</flux:heading>
        <flux:subheading>{{ __('Update details for') }} {{ $first_name }} {{ $last_name }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Institutional Information') }}</flux:legend>
            <div class="grid gap-6">
                @if (!auth()->user()->institution_id)
                <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @endif

            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Personal Information') }}</flux:legend>
            <div class="grid gap-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="first_name" :label="__('First Name')" required />
                    <flux:input wire:model="last_name" :label="__('Last Name')" required />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:input wire:model="email" :label="__('Email')" type="email" required />
                    <flux:input wire:model="phone" :label="__('Phone')" />
                </div>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Professional Details') }}</flux:legend>
            <div class="grid gap-6">
                <flux:input wire:model="staff_number" :label="__('Staff Number')" readonly />

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <flux:select wire:model="role_id" :label="__('Role')" required>
                        <flux:select.option value="null">{{ __('Select role...') }}</flux:select.option>
                        @foreach ($roles as $role)
                        <flux:select.option :value="$role->role_id">{{ $role->role_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="designation" :label="__('Designation')" required />
                </div>
                <flux:select wire:model="status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
                    <flux:select.option value="retired">{{ __('Retired') }}</flux:select.option>
                </flux:select>
            </div>
        </flux:fieldset>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('cms.staff.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Update Staff') }}</flux:button>
        </div>
    </form>
</div>