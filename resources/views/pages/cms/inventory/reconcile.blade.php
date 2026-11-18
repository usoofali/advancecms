<?php

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryStockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Stock Count & Updates')] class extends Component
{
    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public ?int $location_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    public string $adjustment_type = 'recount';

    public string $notes = '';

    /** @var array<int, int|string> */
    public array $counts = [];

    public function mount(): void
    {
        Gate::authorize('inventory.reconcile');

        $user = auth()->user();

        if (! $this->institution_id && $user->institution_id) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id && $user->hasRole('Super Admin')) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }

        // Auto-select first accessible location if none specified or validate access
        $accessible = InventoryLocation::query()
            ->accessibleBy($user, $this->institution_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($this->location_id) {
            $selected = $accessible->firstWhere('id', $this->location_id);
            if (! $selected) {
                abort(403, 'You do not have custodial access to the requested inventory location.');
            }
        } elseif ($accessible->isNotEmpty()) {
            $this->location_id = $accessible->first()->id;
        }

        $this->loadCountsForLocation();
    }

    public function updatedInstitutionId(): void
    {
        $accessible = InventoryLocation::query()
            ->accessibleBy(auth()->user(), $this->institution_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->first();

        $this->location_id = $accessible?->id;
        $this->loadCountsForLocation();
    }

    public function updatedLocationId(): void
    {
        $instId = $this->resolveInstitutionId();
        if ($instId && $this->location_id) {
            $location = InventoryLocation::where('institution_id', $instId)->find($this->location_id);
            if (! $location || ! $location->isAccessibleBy(auth()->user())) {
                abort(403, 'Unauthorized location selection.');
            }
        }

        $this->loadCountsForLocation();
    }

    protected function resolveInstitutionId(): ?int
    {
        return auth()->user()->hasRole('Super Admin')
            ? $this->institution_id
            : auth()->user()->institution_id;
    }

    public function loadCountsForLocation(): void
    {
        $this->counts = [];
        $instId = $this->resolveInstitutionId();

        if (! $instId || ! $this->location_id) {
            return;
        }

        $location = InventoryLocation::where('institution_id', $instId)->find($this->location_id);
        if (! $location || ! $location->isAccessibleBy(auth()->user())) {
            return;
        }

        $stocks = InventoryStock::where('inventory_location_id', $this->location_id)
            ->pluck('quantity', 'inventory_item_id');

        $items = InventoryItem::where('institution_id', $instId)
            ->where('status', 'active')
            ->pluck('id');

        foreach ($items as $itemId) {
            $this->counts[$itemId] = (int) ($stocks[$itemId] ?? 0);
        }
    }

    public function adjustQuickCount(int $itemId, int $delta): void
    {
        $current = (int) ($this->counts[$itemId] ?? 0);
        $this->counts[$itemId] = max(0, $current + $delta);
    }

    public function saveItemCount(int $itemId): void
    {
        Gate::authorize('inventory.reconcile');

        $user = auth()->user();
        $instId = $this->resolveInstitutionId();
        if (! $instId || ! $this->location_id) {
            return;
        }

        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($this->location_id);
        if (! $location->isAccessibleBy($user)) {
            abort(403, 'Unauthorized location reconciliation attempt.');
        }

        $item = InventoryItem::where('institution_id', $instId)->findOrFail($itemId);

        $this->validate([
            "counts.{$itemId}" => ['required', 'integer', 'min:0', 'max:10000000'],
            'adjustment_type' => ['required', 'in:recount,initial_stock,replenishment,consumption,damage_writeoff'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            "counts.{$itemId}.min" => __('On-hand quantity cannot be negative.'),
            "counts.{$itemId}.integer" => __('On-hand quantity must be a whole number.'),
        ]);

        $newQty = (int) $this->counts[$itemId];

        DB::transaction(function () use ($instId, $location, $item, $newQty, $user) {
            $stock = InventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id,
                ],
                [
                    'institution_id' => $instId,
                    'quantity' => 0,
                ]
            );

            $prevQty = (int) $stock->quantity;
            $variance = $newQty - $prevQty;

            $stock->update([
                'quantity' => $newQty,
                'last_counted_at' => now(),
                'last_counted_by' => $user->id,
            ]);

            InventoryStockAdjustment::create([
                'institution_id' => $instId,
                'inventory_stock_id' => $stock->id,
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'previous_quantity' => $prevQty,
                'new_quantity' => $newQty,
                'variance' => $variance,
                'adjustment_type' => $this->adjustment_type,
                'notes' => $this->notes ?: null,
                'recorded_by' => $user->id,
            ]);
        });

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __(':item balance updated to :qty :unit in :loc.', [
                'item' => $item->name,
                'qty' => $newQty,
                'unit' => $item->unit_of_measure,
                'loc' => $location->name,
            ]),
        ]);
    }

    public function saveAllChanged(): void
    {
        Gate::authorize('inventory.reconcile');

        $user = auth()->user();
        $instId = $this->resolveInstitutionId();
        if (! $instId || ! $this->location_id) {
            return;
        }

        $location = InventoryLocation::where('institution_id', $instId)->findOrFail($this->location_id);
        if (! $location->isAccessibleBy($user)) {
            abort(403, 'Unauthorized location reconciliation attempt.');
        }

        $this->validate([
            'counts.*' => ['required', 'integer', 'min:0', 'max:10000000'],
            'adjustment_type' => ['required', 'in:recount,initial_stock,replenishment,consumption,damage_writeoff'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'counts.*.min' => __('Quantities cannot be negative.'),
            'counts.*.integer' => __('All quantities must be whole numbers.'),
        ]);

        $updatedCount = 0;

        DB::transaction(function () use ($instId, $location, $user, &$updatedCount) {
            $existingStocks = InventoryStock::where('inventory_location_id', $location->id)
                ->get()
                ->keyBy('inventory_item_id');

            $validItemIds = InventoryItem::where('institution_id', $instId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            foreach ($this->counts as $itemId => $value) {
                if (! in_array((int) $itemId, $validItemIds, true)) {
                    continue;
                }

                $newQty = (int) $value;
                $existing = $existingStocks->get((int) $itemId);
                $prevQty = $existing ? (int) $existing->quantity : 0;

                // Only persist items whose balance changed or had an explicit initial stock > 0
                if ($existing && $prevQty === $newQty) {
                    continue;
                }
                if (! $existing && $newQty === 0) {
                    continue;
                }

                $stock = $existing ?? InventoryStock::create([
                    'institution_id' => $instId,
                    'inventory_item_id' => (int) $itemId,
                    'inventory_location_id' => $location->id,
                    'quantity' => 0,
                ]);

                $stock->update([
                    'quantity' => $newQty,
                    'last_counted_at' => now(),
                    'last_counted_by' => $user->id,
                ]);

                InventoryStockAdjustment::create([
                    'institution_id' => $instId,
                    'inventory_stock_id' => $stock->id,
                    'inventory_item_id' => (int) $itemId,
                    'inventory_location_id' => $location->id,
                    'previous_quantity' => $prevQty,
                    'new_quantity' => $newQty,
                    'variance' => $newQty - $prevQty,
                    'adjustment_type' => $this->adjustment_type,
                    'notes' => $this->notes ?: null,
                    'recorded_by' => $user->id,
                ]);

                $updatedCount++;
            }
        });

        $this->notes = '';

        $this->dispatch('notify', [
            'type' => $updatedCount > 0 ? 'success' : 'info',
            'message' => $updatedCount > 0
                ? __(':count item balances reconciled in :loc.', ['count' => $updatedCount, 'loc' => $location->name])
                : __('No balance changes detected to save.'),
        ]);
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $accessibleLocations = $instId
            ? InventoryLocation::query()
                ->accessibleBy($user, $instId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : collect();

        $selectedLocation = $this->location_id
            ? $accessibleLocations->firstWhere('id', $this->location_id)
            : null;

        $items = $instId && $selectedLocation
            ? InventoryItem::query()
                ->with(['category', 'stocks' => fn ($q) => $q->where('inventory_location_id', $selectedLocation->id)])
                ->where('institution_id', $instId)
                ->where('status', 'active')
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('name', 'like', "%{$this->search}%")
                            ->orWhere('code', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->categoryFilter, fn ($q) => $q->where('inventory_category_id', $this->categoryFilter))
                ->orderBy('name')
                ->get()
            : collect();

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'accessibleLocations' => $accessibleLocations,
            'selectedLocation' => $selectedLocation,
            'categories' => $instId ? InventoryCategory::where('institution_id', $instId)->where('status', 'active')->orderBy('name')->get() : collect(),
            'items' => $items,
            'adjustmentTypes' => InventoryStockAdjustment::adjustmentTypeLabels(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Stock Count & Updates') }}</flux:heading>
            <flux:subheading>{{ __('Select a room and enter the current quantity on hand') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-60">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if($selectedLocation)
                @can('inventory.reports')
                    <flux:button variant="ghost" icon="printer"
                        :href="route('cms.inventory.print-report', ['location_id' => $selectedLocation->id])" target="_blank" class="w-full sm:w-auto">
                        {{ __('Print Room Sheet') }}
                    </flux:button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Location & Reconciliation Controls --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 shadow-xs space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <flux:select wire:model.live="location_id" :label="__('Location / Room')">
                @if($accessibleLocations->isEmpty())
                    <flux:select.option value="">{{ __('No locations assigned to you') }}</flux:select.option>
                @else
                    @foreach($accessibleLocations as $loc)
                        <flux:select.option :value="$loc->id">{{ $loc->name }} {{ $loc->code ? "({$loc->code})" : '' }}</flux:select.option>
                    @endforeach
                @endif
            </flux:select>

            <flux:select wire:model="adjustment_type" :label="__('Update Reason')">
                @foreach($adjustmentTypes as $key => $label)
                    <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Filter Item')" icon="magnifying-glass" :placeholder="__('Item name or code...')" clearable />

            <flux:select wire:model.live="categoryFilter" :label="__('Category')">
                <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-700">
            <div class="flex-1">
                <flux:input wire:model="notes" :label="__('Notes (Optional)')" :placeholder="__('e.g. Monthly room check / new supply received')" />
            </div>
            @if($selectedLocation && $items->isNotEmpty())
                <flux:button variant="primary" icon="check" wire:click="saveAllChanged" class="w-full sm:w-auto shrink-0">
                    {{ __('Save All') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if(!$selectedLocation)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center text-zinc-500">
            <flux:icon.lock-closed class="size-10 mx-auto mb-3 opacity-30" />
            <p class="font-semibold text-zinc-800 dark:text-zinc-200">{{ __('No Assigned Locations') }}</p>
            <p class="text-xs mt-1">{{ __('You have not been assigned access to any active location yet.') }}</p>
        </div>
    @else
        {{-- Desktop Table (md+) --}}
        <div class="hidden md:block">
            <flux:card>
                <div class="relative overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Item') }}</flux:table.column>
                            <flux:table.column>{{ __('Category') }}</flux:table.column>
                            <flux:table.column class="text-center">{{ __('Current Balance') }}</flux:table.column>
                            <flux:table.column class="text-center">{{ __('New Quantity') }}</flux:table.column>
                            <flux:table.column class="text-right">{{ __('Action') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($items as $item)
                                @php
                                    $currentStock = $item->stocks->first();
                                    $currentQty = $currentStock ? (int) $currentStock->quantity : 0;
                                    $enteredQty = (int) ($counts[$item->id] ?? $currentQty);
                                    $isDirty = $enteredQty !== $currentQty;
                                @endphp
                                <flux:table.row :key="$item->id" class="{{ $isDirty ? 'bg-amber-50/40 dark:bg-amber-950/10' : '' }}">
                                    <flux:table.cell>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                        <div class="text-xs font-mono text-zinc-500">{{ $item->code }} &bull; {{ $item->unit_of_measure }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$item->category?->classification === 'durable' ? 'indigo' : 'teal'">
                                            {{ $item->category?->name }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center font-bold text-zinc-700 dark:text-zinc-300">
                                        {{ number_format($currentQty) }}
                                        @if($currentStock?->last_counted_at)
                                            <div class="text-[10px] font-normal text-zinc-400">
                                                {{ $currentStock->last_counted_at->diffForHumans() }}
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center justify-center gap-1.5">
                                            <flux:button size="xs" variant="ghost" icon="minus" wire:click="adjustQuickCount({{ $item->id }}, -1)" />
                                            <input type="number" min="0" step="1" wire:model="counts.{{ $item->id }}"
                                                class="w-24 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-center text-sm font-bold text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                            <flux:button size="xs" variant="ghost" icon="plus" wire:click="adjustQuickCount({{ $item->id }}, 1)" />
                                        </div>
                                        @error("counts.{$item->id}")
                                            <div class="text-[11px] text-red-600 text-center mt-1">{{ $message }}</div>
                                        @enderror
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        <flux:button size="xs" :variant="$isDirty ? 'primary' : 'ghost'" wire:click="saveItemCount({{ $item->id }})">
                                            {{ $isDirty ? __('Update') : __('Verify') }}
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">
                                        {{ __('No active catalogue items found matching your filter.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>

        {{-- Mobile Touch-Friendly Card List (< md) --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($items as $item)
                @php
                    $currentStock = $item->stocks->first();
                    $currentQty = $currentStock ? (int) $currentStock->quantity : 0;
                    $enteredQty = (int) ($counts[$item->id] ?? $currentQty);
                    $isDirty = $enteredQty !== $currentQty;
                @endphp
                <div wire:key="reconcile-card-{{ $item->id }}" class="rounded-xl border {{ $isDirty ? 'border-amber-400 dark:border-amber-600 bg-amber-50/30 dark:bg-amber-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800' }} p-4 shadow-xs space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                            <div class="text-xs font-mono text-zinc-500">{{ $item->code }} &bull; {{ $item->unit_of_measure }}</div>
                        </div>
                        <flux:badge size="sm" :color="$item->category?->classification === 'durable' ? 'indigo' : 'teal'">
                            {{ $item->category?->name }}
                        </flux:badge>
                    </div>

                    <div class="flex items-center justify-between text-xs text-zinc-500 pt-1">
                        <span>{{ __('Recorded Balance:') }} <strong class="text-zinc-900 dark:text-white">{{ number_format($currentQty) }}</strong></span>
                        @if($isDirty)
                            <span class="font-bold text-amber-600 dark:text-amber-400">
                                {{ ($enteredQty - $currentQty) > 0 ? '+'.($enteredQty - $currentQty) : ($enteredQty - $currentQty) }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="filled" icon="minus" wire:click="adjustQuickCount({{ $item->id }}, -1)" />
                            <input type="number" min="0" step="1" wire:model="counts.{{ $item->id }}"
                                class="w-20 rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-2 py-1.5 text-center text-sm font-bold text-zinc-900 dark:text-white" />
                            <flux:button size="sm" variant="filled" icon="plus" wire:click="adjustQuickCount({{ $item->id }}, 1)" />
                        </div>

                        <flux:button size="sm" :variant="$isDirty ? 'primary' : 'ghost'" wire:click="saveItemCount({{ $item->id }})">
                            {{ $isDirty ? __('Save') : __('Verify') }}
                        </flux:button>
                    </div>
                    @error("counts.{$item->id}")
                        <div class="text-xs text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                    {{ __('No items match your filter.') }}
                </div>
            @endforelse
        </div>
    @endif
</div>
