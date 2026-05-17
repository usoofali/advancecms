<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\Role;
use App\Models\GradingSystem;
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
    public int|string|null $grading_system_id = null;
    public int $max_session_units = 24;

    // Scoped Role Assignment properties
    public int|string|null $assign_user_id = null;
    public int|string|null $assign_role_id = null;

    public function mount(Department $department): void
    {
        Gate::authorize('departments.edit');

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
        $this->grading_system_id = $department->grading_system_id;
        $this->max_session_units = $department->max_session_units ?? 24;
    }

    public function save(): void
    {
        Gate::authorize('departments.edit');

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

        $this->department->update($validated);

        session()->flash('success', 'Department updated successfully.');

        $this->redirect(route('cms.departments.index'), navigate: true);
    }

    public function assignRole(): void
    {
        Gate::authorize('departments.assign_roles');

        $this->validate([
            'assign_user_id' => ['required', 'exists:users,id'],
            'assign_role_id' => ['required', 'exists:roles,role_id'],
        ]);

        $user = \App\Models\User::findOrFail($this->assign_user_id);
        $role = Role::where('role_id', $this->assign_role_id)->firstOrFail();
        
        $user->assignScopedRole($role->role_name, $this->department);
        
        $this->assign_user_id = null;
        $this->assign_role_id = null;
        
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assigned successfully.']);
    }

    public function removeAssignedRole(int $userId, int $roleId): void
    {
        Gate::authorize('departments.assign_roles');

        $user = \App\Models\User::findOrFail($userId);
        $role = Role::findOrFail($roleId);
        
        $user->removeScopedRole($role->role_name, $this->department);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assignment removed.']);
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
            'allUsers' => \App\Models\User::where('institution_id', $this->institution_id)
                ->whereHas('staff') // Security constraint: Only staff can be assigned administrative roles
                ->with('roles')
                ->orderBy('name')
                ->get(),
            'allRoles' => Role::where('role_name', '!=', 'Super Admin')->orderBy('role_name')->get(),
            'assignedUsers' => \Illuminate\Support\Facades\DB::table('model_user_roles')
                ->join('users', 'model_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'model_user_roles.role_id', '=', 'roles.role_id')
                ->where('model_type', $this->department->getMorphClass())
                ->where('model_id', $this->department->id)
                ->select('users.id as user_id', 'users.name as user_name', 'roles.role_id', 'roles.role_name')
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
            <flux:button type="submit" variant="primary">{{ __('Update Department') }}</flux:button>
        </div>
    </form>

    @can('departments.assign_roles')
    <div class="mt-8 border-t border-zinc-200 dark:border-zinc-800 pt-8">
        <flux:heading size="lg" class="mb-4">{{ __('Assigned Scoped Roles') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Dynamically assign users to specific roles strictly within this department.') }}</flux:subheading>

        <div class="bg-zinc-50 dark:bg-zinc-900 rounded-xl p-6 border border-zinc-200 dark:border-zinc-800 mb-6">
            <form wire:submit="assignRole" class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1 w-full">
                    <flux:select wire:model="assign_user_id" :label="__('User')" searchable required>
                        <flux:select.option value="">{{ __('Search or select user...') }}</flux:select.option>
                        @foreach ($allUsers as $u)
                            <flux:select.option :value="$u->id">
                                {{ $u->name }} @if($u->roles->isNotEmpty()) ({{ $u->roles->pluck('role_name')->implode(', ') }}) @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex-1 w-full">
                    <flux:select wire:model="assign_role_id" :label="__('Role')" searchable required>
                        <flux:select.option value="">{{ __('Search or select role...') }}</flux:select.option>
                        @foreach ($allRoles as $r)
                            <flux:select.option :value="$r->role_id">{{ $r->role_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button type="submit" variant="primary">{{ __('Assign') }}</flux:button>
            </form>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($assignedUsers as $assignment)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $assignment->user_name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="indigo" size="sm">{{ $assignment->role_name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="removeAssignedRole({{ $assignment->user_id }}, {{ $assignment->role_id }})" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-500">
                            {{ __('No users are explicitly assigned roles for this department.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @endcan
</div>
</div>