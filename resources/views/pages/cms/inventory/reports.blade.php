<?php

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStockAdjustment;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] #[Title('Stock Reports')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'holdings';

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $locationFilter = '';

    #[Url]
    public string $classificationFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $stock_filter = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('inventory.reports');

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

    public function updatingLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatingClassificationFilter(): void
    {
        $this->categoryFilter = '';
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStockFilter(): void
    {
        $this->resetPage();
    }

    protected function resolveInstitutionId(): ?int
    {
        return auth()->user()->hasRole('Super Admin')
            ? $this->institution_id
            : auth()->user()->institution_id;
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('inventory.reports');

        $user = auth()->user();
        $instId = $this->resolveInstitutionId();
        $accessibleLocationIds = InventoryLocation::query()
            ->accessibleBy($user, $instId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $items = $this->buildFilteredItemsQuery($instId, $accessibleLocationIds)->get();

        $filename = 'inventory-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Item Code',
                'Item Title',
                'Classification',
                'Category',
                'Unit of Measure',
                'Min Safe Threshold',
                'Balance (Filtered Scope)',
                'Status',
                'Room Breakdown',
            ]);

            foreach ($items as $item) {
                $qty = (int) $item->stocks->sum('quantity');
                $isLow = $item->isBelowThreshold($qty);
                $breakdown = $item->stocks
                    ->map(fn ($s) => ($s->location?->name ?? 'Unknown').": {$s->quantity}")
                    ->implode(' | ');

                fputcsv($handle, [
                    $item->code,
                    $item->name,
                    ucfirst($item->category?->classification ?? ''),
                    $item->category?->name ?? '',
                    $item->unit_of_measure,
                    $item->min_stock_threshold,
                    $qty,
                    $isLow ? 'LOW STOCK' : 'OK',
                    $breakdown,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildFilteredItemsQuery(?int $instId, array $accessibleLocationIds)
    {
        $targetLocationIds = $this->locationFilter
            ? array_values(array_intersect($accessibleLocationIds, [(int) $this->locationFilter]))
            : $accessibleLocationIds;

        $query = InventoryItem::query()
            ->with([
                'category',
                'stocks' => fn ($q) => $q->with('location')->whereIn('inventory_location_id', $targetLocationIds),
            ])
            ->where('institution_id', $instId ?? 0)
            ->where('status', 'active')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->when($this->classificationFilter, fn ($q) => $q->whereHas('category', fn ($cq) => $cq->where('classification', $this->classificationFilter)))
            ->when($this->categoryFilter, fn ($q) => $q->where('inventory_category_id', $this->categoryFilter))
            ->orderBy('name');

        if ($this->stock_filter === 'low_stock') {
            $query->whereRaw('(
                SELECT COALESCE(SUM(inventory_stocks.quantity), 0)
                FROM inventory_stocks
                INNER JOIN inventory_locations ON inventory_locations.id = inventory_stocks.inventory_location_id
                WHERE inventory_stocks.inventory_item_id = inventory_items.id
                AND inventory_locations.status = ?
            ) < inventory_items.min_stock_threshold AND inventory_items.min_stock_threshold > 0', ['active']);
        } elseif ($this->stock_filter === 'zero_stock') {
            $query->whereRaw('(
                SELECT COALESCE(SUM(inventory_stocks.quantity), 0)
                FROM inventory_stocks
                WHERE inventory_stocks.inventory_item_id = inventory_items.id
                AND inventory_stocks.inventory_location_id IN ('.(empty($targetLocationIds) ? '0' : implode(',', array_map('intval', $targetLocationIds))).')
            ) = 0');
        }

        return $query;
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

        $accessibleLocationIds = $accessibleLocations->pluck('id')->toArray();

        $items = $this->buildFilteredItemsQuery($instId, $accessibleLocationIds)->paginate(15);

        $adjustments = InventoryStockAdjustment::query()
            ->with(['item.category', 'location', 'recorder'])
            ->where('institution_id', $instId ?? 0)
            ->when($this->locationFilter, fn ($q) => $q->where('inventory_location_id', $this->locationFilter),
                fn ($q) => $q->whereIn('inventory_location_id', empty($accessibleLocationIds) ? [0] : $accessibleLocationIds))
            ->when($this->search, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(15, pageName: 'adjPage');

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'accessibleLocations' => $accessibleLocations,
            'categories' => $instId ? InventoryCategory::where('institution_id', $instId)->where('status', 'active')->orderBy('name')->get() : collect(),
            'items' => $items,
            'adjustments' => $adjustments,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Stock Reports') }}</flux:heading>
            <flux:subheading>{{ __('View stock balances by room, check low-stock items, and review recent stock updates') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-56">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:button variant="ghost" icon="arrow-down-tray" wire:click="exportCsv" class="w-full sm:w-auto">
                {{ __('Export CSV') }}
            </flux:button>

            <flux:button variant="primary" icon="printer"
                :href="route('cms.inventory.print-report', [
                    'institution_id' => $institution_id,
                    'location_id' => $locationFilter,
                    'classification' => $classificationFilter,
                    'category_id' => $categoryFilter,
                    'stock_filter' => $stock_filter,
                ])"
                target="_blank" class="w-full sm:w-auto">
                {{ __('Print Stock Sheet') }}
            </flux:button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto">
        <button type="button" wire:click="$set('tab', 'holdings')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'holdings' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.document-chart-bar class="size-4" />
            {{ __('Stock Balances') }}
        </button>

        <button type="button" wire:click="$set('tab', 'adjustments')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'adjustments' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.clock class="size-4" />
            {{ __('Stock History') }}
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search item or code...')" clearable />

        <flux:select wire:model.live="locationFilter">
            <flux:select.option value="">{{ __('All Rooms') }}</flux:select.option>
            @foreach($accessibleLocations as $loc)
                <flux:select.option :value="$loc->id">{{ $loc->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="classificationFilter">
            <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
            <flux:select.option value="durable">{{ __('Durable Items') }}</flux:select.option>
            <flux:select.option value="consumable">{{ __('Consumables') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="categoryFilter">
            <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
            @foreach($categories as $cat)
                <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="stock_filter">
            <flux:select.option value="">{{ __('All Stock Levels') }}</flux:select.option>
            <flux:select.option value="low_stock">{{ __('Low Stock') }}</flux:select.option>
            <flux:select.option value="zero_stock">{{ __('Out of Stock (0)') }}</flux:select.option>
        </flux:select>
    </div>

    @if($tab === 'holdings')
        {{-- Desktop Table --}}
        <div class="hidden md:block">
            <flux:card>
                <div class="relative overflow-x-auto">
                    <flux:table :paginate="$items">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Item') }}</flux:table.column>
                            <flux:table.column>{{ __('Category') }}</flux:table.column>
                            <flux:table.column class="text-center">{{ __('Min Level') }}</flux:table.column>
                            <flux:table.column class="text-center">{{ __('Quantity') }}</flux:table.column>
                            <flux:table.column>{{ __('By Room') }}</flux:table.column>
                            <flux:table.column class="text-right">{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($items as $item)
                                @php
                                    $qty = (int) $item->stocks->sum('quantity');
                                    $isLow = $item->isBelowThreshold($qty);
                                @endphp
                                <flux:table.row :key="$item->id">
                                    <flux:table.cell>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                        <div class="text-xs font-mono text-zinc-500">{{ $item->code }} &bull; {{ $item->unit_of_measure }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item->category?->name }}</div>
                                        <div class="text-zinc-500 capitalize">{{ $item->category?->classification }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ number_format($item->min_stock_threshold) }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-center font-black text-zinc-900 dark:text-white">
                                        {{ number_format($qty) }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($item->stocks->where('quantity', '>', 0) as $s)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-[11px] text-zinc-700 dark:text-zinc-200">
                                                    {{ $s->location?->name }}: <strong class="ml-1">{{ $s->quantity }}</strong>
                                                </span>
                                            @empty
                                                <span class="text-xs text-zinc-400 italic">{{ __('None in stock') }}</span>
                                            @endforelse
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        @if($isLow)
                                            <flux:badge size="sm" color="red">{{ __('Low Stock') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="green">{{ __('In Stock') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                                        {{ __('No inventory records match the selected filters.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>

        {{-- Mobile Cards (< md) --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($items as $item)
                @php
                    $qty = (int) $item->stocks->sum('quantity');
                    $isLow = $item->isBelowThreshold($qty);
                @endphp
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                            <div class="text-xs text-zinc-500 font-mono">{{ $item->code }} &bull; {{ $item->category?->name }}</div>
                        </div>
                        <flux:badge size="sm" :color="$isLow ? 'red' : 'green'">
                            {{ $isLow ? __('Low Stock') : __('OK') }}
                        </flux:badge>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <span>{{ __('Quantity:') }} <strong>{{ number_format($qty) }} {{ $item->unit_of_measure }}</strong></span>
                        <span>{{ __('Min:') }} <strong>{{ number_format($item->min_stock_threshold) }}</strong></span>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                    {{ __('No inventory records match the selected filters.') }}
                </div>
            @endforelse
            <div>{{ $items->links() }}</div>
        </div>
    @else
        {{-- Variance & Reconciliation Audit Log --}}
        <flux:card>
            <div class="relative overflow-x-auto">
                <flux:table :paginate="$adjustments">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Timestamp') }}</flux:table.column>
                        <flux:table.column>{{ __('Location / Item') }}</flux:table.column>
                        <flux:table.column>{{ __('Type / Notes') }}</flux:table.column>
                        <flux:table.column class="text-center">{{ __('Change') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Recorded By') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($adjustments as $adj)
                            <flux:table.row :key="$adj->id">
                                <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                                    {{ $adj->created_at?->format('M d, Y H:i') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $adj->item?->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $adj->location?->name }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs">
                                    <div class="font-medium text-zinc-700 dark:text-zinc-300">{{ $adj->adjustment_type_label }}</div>
                                    @if($adj->notes)
                                        <div class="text-zinc-500 italic">{{ $adj->notes }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-center font-mono text-xs whitespace-nowrap">
                                    {{ $adj->previous_quantity }} &rarr; <strong>{{ $adj->new_quantity }}</strong>
                                    <flux:badge size="sm" class="ml-1" :color="$adj->variance > 0 ? 'green' : ($adj->variance < 0 ? 'red' : 'zinc')">
                                        {{ $adj->variance > 0 ? '+'.$adj->variance : $adj->variance }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-right text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $adj->recorder?->name ?? __('System') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">
                                    {{ __('No stock updates recorded yet.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    @endif
</div>

