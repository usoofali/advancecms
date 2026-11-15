<?php

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryStockAdjustment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Inventory Overview')] class extends Component
{
    #[Url]
    public ?int $institution_id = null;

    public function mount(): void
    {
        Gate::authorize('inventory.view');

        if (! $this->institution_id && auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        } elseif (! $this->institution_id && auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $user->hasRole('Super Admin') ? $this->institution_id : $user->institution_id;

        if (! $instId) {
            return [
                'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
                'stats' => [
                    'total_items' => 0,
                    'durable_items' => 0,
                    'consumable_items' => 0,
                    'accessible_locations' => 0,
                    'total_units_on_hand' => 0,
                    'low_stock_count' => 0,
                ],
                'lowStockItems' => collect(),
                'accessibleLocations' => collect(),
                'recentAdjustments' => collect(),
            ];
        }

        $accessibleLocationsQuery = InventoryLocation::query()
            ->accessibleBy($user, $instId)
            ->where('status', 'active');

        $accessibleLocationIds = (clone $accessibleLocationsQuery)->pluck('id')->toArray();

        $items = InventoryItem::query()
            ->with(['category', 'stocks' => fn ($q) => $q->whereHas('location', fn ($lq) => $lq->where('status', 'active'))])
            ->where('institution_id', $instId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $lowStockItems = $items->filter(function (InventoryItem $item) {
            $total = (int) $item->stocks->sum('quantity');

            return $item->isBelowThreshold($total);
        })->values();

        $durableCount = $items->filter(fn (InventoryItem $i) => $i->category?->classification === 'durable')->count();
        $consumableCount = $items->filter(fn (InventoryItem $i) => $i->category?->classification === 'consumable')->count();

        $totalUnitsInAccessible = InventoryStock::query()
            ->where('institution_id', $instId)
            ->whereIn('inventory_location_id', $accessibleLocationIds)
            ->sum('quantity');

        $accessibleLocations = (clone $accessibleLocationsQuery)
            ->with(['locatable'])
            ->withCount(['stocks as distinct_items_count' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum('stocks as total_units', 'quantity')
            ->orderBy('name')
            ->take(8)
            ->get();

        $recentAdjustments = InventoryStockAdjustment::query()
            ->with(['item.category', 'location', 'recorder'])
            ->where('institution_id', $instId)
            ->whereIn('inventory_location_id', $accessibleLocationIds)
            ->latest()
            ->take(8)
            ->get();

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'stats' => [
                'total_items' => $items->count(),
                'durable_items' => $durableCount,
                'consumable_items' => $consumableCount,
                'accessible_locations' => count($accessibleLocationIds),
                'total_units_on_hand' => (int) $totalUnitsInAccessible,
                'low_stock_count' => $lowStockItems->count(),
            ],
            'lowStockItems' => $lowStockItems->take(6),
            'accessibleLocations' => $accessibleLocations,
            'recentAdjustments' => $recentAdjustments,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Inventory Overview') }}</flux:heading>
            <flux:subheading>{{ __('Track items, room stock balances, and low-stock alerts') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-64" :placeholder="__('Select Institution...')">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @can('inventory.reconcile')
                <flux:button variant="primary" icon="clipboard-document-check" :href="route('cms.inventory.reconcile')" wire:navigate class="w-full sm:w-auto">
                    {{ __('Update Stock Count') }}
                </flux:button>
            @endcan

            @can('inventory.reports')
                <flux:button variant="ghost" icon="document-chart-bar" :href="route('cms.inventory.reports')" wire:navigate class="w-full sm:w-auto">
                    {{ __('Stock Reports') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Proactive Low-Stock Alert Banner (INV-08, INV-09) --}}
    @if($stats['low_stock_count'] > 0)
        <div class="rounded-xl border border-amber-300 dark:border-amber-800/80 bg-amber-50/90 dark:bg-amber-950/30 p-4 sm:p-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 shrink-0">
                        <flux:icon.exclamation-triangle class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                            {{ __('Low Stock Alert') }} ({{ $stats['low_stock_count'] }} {{ Str::plural('item', $stats['low_stock_count']) }} {{ __('running low') }})
                        </h3>
                        <p class="text-xs text-amber-700 dark:text-amber-300/80 mt-0.5">
                            {{ __('Total stock across active rooms has dropped below the minimum level.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @can('inventory.reports')
                        <flux:button size="xs" variant="filled" :href="route('cms.inventory.reports', ['stock_filter' => 'low_stock'])" wire:navigate icon="arrow-down-tray">
                            {{ __('View Low Stock') }}
                        </flux:button>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                @foreach($lowStockItems as $lowItem)
                    @php
                        $cumQty = (int) $lowItem->stocks->sum('quantity');
                    @endphp
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-white/90 dark:bg-zinc-900/80 border border-amber-200/80 dark:border-amber-800/50 px-3 py-2">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $lowItem->name }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">
                                {{ $lowItem->code }} &bull; {{ $lowItem->category?->name }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <flux:badge size="sm" color="red">
                                {{ $cumQty }} / {{ $lowItem->min_stock_threshold }} {{ $lowItem->unit_of_measure }}
                            </flux:badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <flux:card class="flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Total Items') }}</div>
                <div class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-white mt-1">{{ number_format($stats['total_items']) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ $stats['durable_items'] }} {{ __('Durable') }}</span> &bull;
                    <span class="font-medium text-teal-600 dark:text-teal-400">{{ $stats['consumable_items'] }} {{ __('Consumable') }}</span>
                </div>
            </div>
            <div class="p-3 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 shrink-0">
                <flux:icon.clipboard-document-list class="size-6" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Assigned Locations') }}</div>
                <div class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-white mt-1">{{ number_format($stats['accessible_locations']) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Labs, stores & offices') }}</div>
            </div>
            <div class="p-3 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400 shrink-0">
                <flux:icon.building-office-2 class="size-6" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Total Stock Units') }}</div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['total_units_on_hand']) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Current balance on hand') }}</div>
            </div>
            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 shrink-0">
                <flux:icon.archive-box class="size-6" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Low Stock') }}</div>
                <div class="text-2xl sm:text-3xl font-black {{ $stats['low_stock_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-white' }} mt-1">
                    {{ number_format($stats['low_stock_count']) }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Items needing restock') }}</div>
            </div>
            <div class="p-3 rounded-xl {{ $stats['low_stock_count'] > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }} shrink-0">
                <flux:icon.exclamation-triangle class="size-6" />
            </div>
        </flux:card>
    </div>

    {{-- Two-Column Responsive Layout: Accessible Locations & Recent Stock Updates --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Accessible Locations Summary --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-zinc-200 dark:border-zinc-700">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Locations & Rooms') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Labs, stores, and offices you can view or update') }}</p>
                </div>
                @can('inventory_locations.view')
                    <flux:button size="xs" variant="ghost" :href="route('cms.inventory.locations')" wire:navigate>
                        {{ __('Manage All') }}
                    </flux:button>
                @endcan
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700 flex-1">
                @forelse($accessibleLocations as $location)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-zinc-50/70 dark:hover:bg-zinc-900/30 transition-colors">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $location->name }}</span>
                                @if($location->code)
                                    <span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $location->code }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 flex flex-wrap items-center gap-2">
                                <span>{{ $location->location_type_label }}</span>
                                @if($location->locatable)
                                    <span>&bull; {{ $location->locatable->name ?? class_basename($location->locatable_type) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                            <div class="text-left sm:text-right">
                                <div class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ number_format((int) $location->total_units) }} {{ __('units') }}
                                </div>
                                <div class="text-[11px] text-zinc-500">
                                    {{ $location->distinct_items_count }} {{ Str::plural('item', $location->distinct_items_count) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                @can('inventory.reconcile')
                                    <flux:button size="xs" variant="ghost" icon="clipboard-document-check"
                                        :href="route('cms.inventory.reconcile', ['location_id' => $location->id])" wire:navigate
                                        title="{{ __('Reconcile Stock') }}" />
                                @endcan
                                @can('inventory.reports')
                                    <flux:button size="xs" variant="ghost" icon="printer"
                                        :href="route('cms.inventory.print-report', ['location_id' => $location->id])" target="_blank"
                                        title="{{ __('Print Room Stock Sheet') }}" />
                                @endcan
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon.building-office-2 class="size-8 mx-auto mb-2 opacity-30" />
                        <p>{{ __('No active inventory locations assigned or configured yet.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Stock Updates Feed --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 py-3.5 border-b border-zinc-200 dark:border-zinc-700">
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Recent Stock Updates') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Latest room counts and balance changes') }}</p>
                </div>
                @can('inventory.reports')
                    <flux:button size="xs" variant="ghost" :href="route('cms.inventory.reports', ['tab' => 'adjustments'])" wire:navigate>
                        {{ __('View History') }}
                    </flux:button>
                @endcan
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700 flex-1">
                @forelse($recentAdjustments as $adj)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-zinc-50/70 dark:hover:bg-zinc-900/30 transition-colors">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $adj->item?->name ?? __('Deleted Item') }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $adj->location?->name }}</span>
                                &bull; {{ $adj->recorder?->name ?? __('System') }}
                                &bull; {{ $adj->created_at?->diffForHumans() }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0">
                            <span class="text-xs font-mono text-zinc-500">
                                {{ $adj->previous_quantity }} &rarr; <strong class="text-zinc-900 dark:text-white">{{ $adj->new_quantity }}</strong>
                            </span>
                            <flux:badge size="sm" :color="$adj->variance > 0 ? 'green' : ($adj->variance < 0 ? 'red' : 'zinc')">
                                {{ $adj->variance > 0 ? '+'.$adj->variance : $adj->variance }}
                            </flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        <flux:icon.clock class="size-8 mx-auto mb-2 opacity-30" />
                        <p>{{ __('No stock reconciliations recorded yet.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

