<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\InventoryLocation;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Locations & Rooms')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    // Location Form Modal
    public bool $showLocationModal = false;

    public ?int $editingLocationId = null;

    public string $loc_name = '';

    public string $loc_code = '';

    public string $loc_type = 'lab_clinic';

    public string $loc_building = '';

    public string $loc_owner_type = '';

    public int|string|null $loc_owner_id = '';

    public string $loc_description = '';

    public string $loc_status = 'active';

    // Officer Custody Assignment Modal (Polymorphic model_user_roles)
    public bool $showCustodyModal = false;

    public ?int $custodyLocationId = null;

    public int|string|null $assign_user_id = '';

    public function mount(): void
    {
        Gate::authorize('inventory_locations.view');

        if (! $this->institution_id && auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        } elseif (! $this->institution_id && auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocOwnerType(): void
    {
        $this->loc_owner_id = '';
    }

    protected function resolveInstitutionId(): ?int
    {
        return auth()->user()->hasRole('Super Admin')
            ? $this->institution_id
            : auth()->user()->institution_id;
    }

    public function openCreateLocationModal(): void
    {
        Gate::authorize('inventory_locations.create');

        $this->editingLocationId = null;
        $this->loc_name = '';
        $this->loc_code = '';
        $this->loc_type = 'lab_clinic';
        $this->loc_building = '';
        $this->loc_owner_type = '';
        $this->loc_owner_id = '';
        $this->loc_description = '';
        $this->loc_status = 'active';
        $this->showLocationModal = true;
    }

    public function openEditLocationModal(int $locationId): void
    {
        Gate::authorize('inventory_locations.edit');

        $instId = $this->resolveInstitutionId();
        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($locationId);

        $this->editingLocationId = $location->id;
        $this->loc_name = $location->name;
        $this->loc_code = $location->code ?? '';
        $this->loc_type = $location->location_type;
        $this->loc_building = $location->building_or_block ?? '';
        $this->loc_owner_type = match ($location->locatable_type) {
            Department::class => 'department',
            Program::class => 'program',
            default => '',
        };
        $this->loc_owner_id = $location->locatable_id ?? '';
        $this->loc_description = $location->description ?? '';
        $this->loc_status = $location->status;
        $this->showLocationModal = true;
    }

    public function saveLocation(): void
    {
        Gate::authorize($this->editingLocationId ? 'inventory_locations.edit' : 'inventory_locations.create');

        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            abort(403, 'Institution context required.');
        }

        $validated = $this->validate([
            'loc_name' => ['required', 'string', 'max:191'],
            'loc_code' => ['nullable', 'string', 'max:50'],
            'loc_type' => ['required', Rule::in(array_keys(InventoryLocation::locationTypeLabels()))],
            'loc_building' => ['nullable', 'string', 'max:191'],
            'loc_owner_type' => ['nullable', 'in:,department,program'],
            'loc_owner_id' => ['nullable', 'integer'],
            'loc_description' => ['nullable', 'string', 'max:1000'],
            'loc_status' => ['required', 'in:active,inactive'],
        ]);

        $locatableType = null;
        $locatableId = null;

        if ($validated['loc_owner_type'] === 'department' && $validated['loc_owner_id']) {
            $dept = Department::where('institution_id', $instId)->findOrFail($validated['loc_owner_id']);
            $locatableType = $dept->getMorphClass();
            $locatableId = $dept->id;
        } elseif ($validated['loc_owner_type'] === 'program' && $validated['loc_owner_id']) {
            $prog = Program::where('institution_id', $instId)->findOrFail($validated['loc_owner_id']);
            $locatableType = $prog->getMorphClass();
            $locatableId = $prog->id;
        }

        $payload = [
            'institution_id' => $instId,
            'name' => $validated['loc_name'],
            'code' => $validated['loc_code'] ? strtoupper(trim($validated['loc_code'])) : null,
            'location_type' => $validated['loc_type'],
            'locatable_type' => $locatableType,
            'locatable_id' => $locatableId,
            'building_or_block' => $validated['loc_building'] ?: null,
            'description' => $validated['loc_description'] ?: null,
            'status' => $validated['loc_status'],
        ];

        if ($this->editingLocationId) {
            $location = InventoryLocation::where('institution_id', $instId)->findOrFail($this->editingLocationId);
            $location->update($payload);
            $msg = __('Inventory location updated successfully.');
        } else {
            InventoryLocation::create($payload);
            $msg = __('Inventory location created successfully.');
        }

        $this->showLocationModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function deleteLocation(int $locationId): void
    {
        Gate::authorize('inventory_locations.delete');

        $instId = $this->resolveInstitutionId();
        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($locationId);
        $location->delete();

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Inventory location removed.')]);
    }

    public function openCustodyModal(int $locationId): void
    {
        Gate::authorize('inventory.assign_officers');

        $instId = $this->resolveInstitutionId();
        InventoryLocation::where('institution_id', $instId)->findOrFail($locationId);

        $this->custodyLocationId = $locationId;
        $this->assign_user_id = '';
        $this->showCustodyModal = true;
    }

    public function assignOfficer(): void
    {
        Gate::authorize('inventory.assign_officers');

        $instId = $this->resolveInstitutionId();
        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($this->custodyLocationId);

        $this->validate([
            'assign_user_id' => ['required', 'exists:users,id'],
        ]);

        $targetUser = User::where('institution_id', $instId)
            ->whereHas('staff')
            ->findOrFail($this->assign_user_id);

        $targetUser->assignScopedRole('Inventory Officer', $location);

        $this->assign_user_id = '';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __(':name assigned as Inventory Officer for :loc.', ['name' => $targetUser->name, 'loc' => $location->name]),
        ]);
    }

    public function removeOfficer(int $userId): void
    {
        Gate::authorize('inventory.assign_officers');

        $instId = $this->resolveInstitutionId();
        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($this->custodyLocationId);
        $targetUser = User::findOrFail($userId);

        $targetUser->removeScopedRole('Inventory Officer', $location);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Officer custody assignment removed.'),
        ]);
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $locations = InventoryLocation::query()
            ->accessibleBy($user, $instId)
            ->with(['locatable'])
            ->withCount(['stocks as items_tracked_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum('stocks as total_units', 'quantity')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('building_or_block', 'like', "%{$this->search}%");
                });
            })
            ->when($this->typeFilter, fn ($q) => $q->where('location_type', $this->typeFilter))
            ->orderBy('location_type')
            ->orderBy('name')
            ->paginate(12);

        // Fetch assigned Inventory Officers per displayed location
        $locationIds = $locations->pluck('id')->toArray();
        $officersByLocation = collect();

        if (! empty($locationIds)) {
            $officersByLocation = DB::table('model_user_roles')
                ->join('users', 'model_user_roles.user_id', '=', 'users.id')
                ->join('roles', 'model_user_roles.role_id', '=', 'roles.role_id')
                ->where('model_user_roles.model_type', (new InventoryLocation)->getMorphClass())
                ->whereIn('model_user_roles.model_id', $locationIds)
                ->where('roles.role_name', 'Inventory Officer')
                ->select('model_user_roles.model_id as location_id', 'users.id as user_id', 'users.name', 'users.email')
                ->get()
                ->groupBy('location_id');
        }

        $custodyLocation = $this->custodyLocationId && $instId
            ? InventoryLocation::where('institution_id', $instId)->find($this->custodyLocationId)
            : null;

        $custodyOfficers = $custodyLocation
            ? $custodyLocation->usersWithScopedRole('Inventory Officer')
            : collect();

        $staffUsers = $instId
            ? User::where('institution_id', $instId)->whereHas('staff')->orderBy('name')->get()
            : collect();

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'locations' => $locations,
            'officersByLocation' => $officersByLocation,
            'locationTypes' => InventoryLocation::locationTypeLabels(),
            'departments' => $instId ? Department::where('institution_id', $instId)->orderBy('name')->get() : collect(),
            'programs' => $instId ? Program::where('institution_id', $instId)->orderBy('name')->get() : collect(),
            'custodyLocation' => $custodyLocation,
            'custodyOfficers' => $custodyOfficers,
            'staffUsers' => $staffUsers,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Locations & Rooms') }}</flux:heading>
            <flux:subheading>{{ __('Manage labs, stores, offices, and assigned room officers') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-60" :placeholder="__('Select Institution...')">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @can('inventory_locations.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateLocationModal" class="w-full sm:w-auto">
                    {{ __('Add Location / Room') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search room, lab, store or block...')" clearable />

        <flux:select wire:model.live="typeFilter">
            <flux:select.option value="">{{ __('All Location Types') }}</flux:select.option>
            @foreach($locationTypes as $key => $label)
                <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-center justify-start lg:justify-end text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Showing') }} {{ $locations->total() }} {{ Str::plural('authorized location', $locations->total()) }}
        </div>
    </div>

    {{-- Responsive Locations Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($locations as $location)
            @php
                $assignedOfficers = $officersByLocation->get($location->id, collect());
            @endphp
            <div wire:key="loc-card-{{ $location->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs flex flex-col justify-between gap-4">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-base text-zinc-900 dark:text-zinc-100">{{ $location->name }}</h3>
                                @if($location->code)
                                    <span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $location->code }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ $location->location_type_label }}
                                @if($location->building_or_block)
                                    &bull; {{ $location->building_or_block }}
                                @endif
                            </div>
                        </div>

                        <flux:badge size="sm" :color="$location->status === 'active' ? 'green' : 'zinc'">
                            {{ ucfirst($location->status) }}
                        </flux:badge>
                    </div>

                    @if($location->locatable)
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 text-xs font-medium">
                            <flux:icon.square-3-stack-3d class="size-3.5" />
                            <span>{{ class_basename($location->locatable_type) }}: {{ $location->locatable->name ?? '—' }}</span>
                        </div>
                    @endif

                    {{-- Balance Summary --}}
                    <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/60 border border-zinc-100 dark:border-zinc-700/60">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-zinc-500">{{ __('Items Held') }}</div>
                            <div class="text-lg font-black text-zinc-900 dark:text-white">{{ number_format($location->items_tracked_count) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-zinc-500">{{ __('Total Units') }}</div>
                            <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ number_format((int) $location->total_units) }}</div>
                        </div>
                    </div>

                    {{-- Assigned Officers --}}
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 mb-1.5">{{ __('Assigned Officers') }}</div>
                        @if($assignedOfficers->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($assignedOfficers as $officer)
                                    <flux:badge size="sm" color="cyan" inset="top bottom">{{ $officer->name }}</flux:badge>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-zinc-400 italic">{{ __('No officer assigned yet') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Actions Footer --}}
                <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-1.5">
                        @can('inventory.reconcile')
                            <flux:button size="xs" variant="primary" icon="clipboard-document-check"
                                :href="route('cms.inventory.reconcile', ['location_id' => $location->id])" wire:navigate>
                                {{ __('Count Stock') }}
                            </flux:button>
                        @endcan
                        @can('inventory.reports')
                            <flux:button size="xs" variant="ghost" icon="printer"
                                :href="route('cms.inventory.print-report', ['location_id' => $location->id])" target="_blank">
                                {{ __('Print Sheet') }}
                            </flux:button>
                        @endcan
                    </div>

                    <div class="flex items-center gap-1">
                        @can('inventory.assign_officers')
                            <flux:button size="xs" variant="ghost" icon="user-plus" wire:click="openCustodyModal({{ $location->id }})" title="{{ __('Assign Officers') }}" />
                        @endcan
                        @can('inventory_locations.edit')
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditLocationModal({{ $location->id }})" title="{{ __('Edit Location') }}" />
                        @endcan
                        @can('inventory_locations.delete')
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteLocation({{ $location->id }})" wire:confirm="{{ __('Delete this location and its stock records?') }}" title="{{ __('Delete') }}" />
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center text-zinc-500">
                {{ __('No inventory locations found matching your permissions or filters.') }}
            </div>
        @endforelse
    </div>

    <div>{{ $locations->links() }}</div>

    {{-- Create / Edit Location Modal --}}
    <flux:modal wire:model.self="showLocationModal" class="max-w-lg w-full">
        <form wire:submit="saveLocation" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingLocationId ? __('Edit Location') : __('Add Inventory Location') }}</flux:heading>
                <flux:subheading>{{ __('Define a lab, store, office, or lecture hall and optionally attach it to a Department or Program.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:input wire:model="loc_name" :label="__('Location / Room Name')" :placeholder="__('e.g. Microbiology Practical Lab 1')" required />
                </div>

                <flux:input wire:model="loc_code" :label="__('Room / Store Code')" :placeholder="__('e.g. LAB-MCB-01')" />

                <flux:select wire:model="loc_type" :label="__('Facility Category')" required>
                    @foreach($locationTypes as $key => $label)
                        <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="loc_building" :label="__('Building / Wing')" :placeholder="__('e.g. Science Block A')" />

                <flux:select wire:model="loc_status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>

                {{-- Polymorphic Locatable Owner --}}
                <flux:select wire:model.live="loc_owner_type" :label="__('Department / Program (Optional)')">
                    <flux:select.option value="">{{ __('General / Shared Facility') }}</flux:select.option>
                    <flux:select.option value="department">{{ __('Department') }}</flux:select.option>
                    <flux:select.option value="program">{{ __('Academic Program') }}</flux:select.option>
                </flux:select>

                @if($loc_owner_type === 'department')
                    <flux:select wire:model="loc_owner_id" :label="__('Select Department')">
                        <flux:select.option value="">{{ __('Choose department...') }}</flux:select.option>
                        @foreach($departments as $dept)
                            <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif($loc_owner_type === 'program')
                    <flux:select wire:model="loc_owner_id" :label="__('Select Program')">
                        <flux:select.option value="">{{ __('Choose program...') }}</flux:select.option>
                        @foreach($programs as $prog)
                            <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="loc_description" :label="__('Notes')" rows="2" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showLocationModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Location') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Assign Location Inventory Officers Modal (Polymorphic model_user_roles) --}}
    <flux:modal wire:model.self="showCustodyModal" class="max-w-lg w-full">
        @if($custodyLocation)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Assign Room Officers') }}</flux:heading>
                    <flux:subheading>{{ __('Choose staff members who can count and update stock in :name.', ['name' => $custodyLocation->name]) }}</flux:subheading>
                </div>

                <form wire:submit="assignOfficer" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700">
                    <div class="flex-1">
                        <flux:select wire:model="assign_user_id" :label="__('Staff Member')" required>
                            <flux:select.option value="">{{ __('Select staff member...') }}</flux:select.option>
                            @foreach($staffUsers as $staffUser)
                                <flux:select.option :value="$staffUser->id">{{ $staffUser->name }} ({{ $staffUser->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button type="submit" variant="primary" icon="user-plus">{{ __('Assign') }}</flux:button>
                </form>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700 max-h-60 overflow-y-auto">
                    @forelse($custodyOfficers as $officer)
                        <div class="py-2.5 flex items-center justify-between gap-2">
                            <div>
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $officer->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $officer->email }}</div>
                            </div>
                            <flux:button size="xs" variant="danger" icon="trash" wire:click="removeOfficer({{ $officer->id }})" />
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-zinc-500">
                            {{ __('No officers assigned to this location yet.') }}
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="ghost" type="button" wire:click="$set('showCustodyModal', false)">{{ __('Done') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

