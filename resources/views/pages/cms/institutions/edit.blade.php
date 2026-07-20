<?php

use App\Models\Institution;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
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
    public ?string $meta = '';
    public $logo;

    // Scoped Role Assignment properties
    public int|string|null $assign_user_id = null;
    public int|string|null $assign_role_id = null;

    public function mount(Institution $institution): void
    {
        Gate::authorize('institutions.edit');

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
        $this->meta = $institution->meta ?? '';
    }

    public function save(): void
    {
        Gate::authorize('institutions.edit');

        $validated = $this->validate([
            'name'                 => ['required', 'string', 'max:255', 'unique:institutions,name,' . $this->institution->id],
            'default_allowance'    => ['required', 'numeric', 'min:0'],
            'acronym'              => ['nullable', 'string', 'max:20'],
            'address'              => ['nullable', 'string'],
            'email'                => ['nullable', 'email', 'max:255'],
            'phone'                => ['nullable', 'string', 'max:30'],
            'established_year'     => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'status'               => ['required', 'in:active,inactive'],
            'meta'                 => ['nullable', 'string'],
            'logo'                 => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->logo) {
            // Delete old logo if exists
            if ($this->institution->logo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->institution->logo_path);
            }
            $validated['logo_path'] = $this->logo->store('institutions/logos', 'public');
        }

        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        $this->institution->update($validated);

        session()->flash('success', 'Institution updated successfully.');

        $this->redirect(route('cms.institutions.index'), navigate: true);
    }

    public function assignRole(): void
    {
        Gate::authorize('institutions.assign_roles');

        $this->validate([
            'assign_user_id' => ['required', 'exists:users,id'],
            'assign_role_id' => ['required', 'exists:roles,role_id'],
        ]);

        $user = \App\Models\User::findOrFail($this->assign_user_id);
        $role = Role::where('role_id', $this->assign_role_id)->firstOrFail();

        $user->assignScopedRole($role->role_name, $this->institution);

        $this->assign_user_id = null;
        $this->assign_role_id = null;

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assigned successfully.']);
    }

    public function removeAssignedRole(int $userId, int $roleId): void
    {
        Gate::authorize('institutions.assign_roles');

        $user = \App\Models\User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        $user->removeScopedRole($role->role_name, $this->institution);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Role assignment removed.']);
    }

    public function with(): array
    {
        return [
            'allUsers' => \App\Models\User::where('institution_id', $this->institution->id)
                ->whereHas('staff')
                ->with('roles')
                ->orderBy('name')
                ->get(),
            'allRoles' => Role::where('role_name', '!=', 'Super Admin')->orderBy('role_name')->get(),
            'assignedUsers' => DB::table('model_user_roles')
                ->join('users', 'model_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'model_user_roles.role_id', '=', 'roles.role_id')
                ->where('model_type', $this->institution->getMorphClass())
                ->where('model_id', $this->institution->id)
                ->select('users.id as user_id', 'users.name as user_name', 'roles.role_id', 'roles.role_name')
                ->get(),
        ];
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
                        
                        <flux:textarea wire:model="meta" :label="__('Meta Information')" :placeholder="__('Additional metadata or JSON configuration')" rows="3" />
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

            @can('institutions.assign_roles')
            <div class="mt-8 border-t border-zinc-200 dark:border-zinc-800 pt-8">
                <flux:heading size="lg" class="mb-4">{{ __('Assigned Scoped Roles') }}</flux:heading>
                <flux:subheading class="mb-6">{{ __('Dynamically assign users to specific roles strictly within this institution.') }}</flux:subheading>

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
                                    {{ __('No users are explicitly assigned roles for this institution.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            @endcan
        </div>
</div>
