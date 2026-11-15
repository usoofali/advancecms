<?php

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Items & Categories')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'items';

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $classificationFilter = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $stockFilter = '';

    // Item Form Modal State
    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public int|string $item_category_id = '';

    public string $item_name = '';

    public string $item_code = '';

    public string $item_unit = 'Pieces';

    public int $item_min_threshold = 5;

    public string $item_description = '';

    public string $item_status = 'active';

    // Category Form Modal State
    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public string $cat_name = '';

    public string $cat_code = '';

    public string $cat_classification = 'durable';

    public string $cat_description = '';

    public string $cat_status = 'active';

    // Item Room Breakdown Modal
    public bool $showBreakdownModal = false;

    public ?int $breakdownItemId = null;

    // Delete Confirmation
    public ?int $deletingItemId = null;

    public ?int $deletingCategoryId = null;

    public function mount(): void
    {
        Gate::authorize('inventory_items.view');

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

    /**
     * Quick-seed universal institutional categories (Durable Assets & Consumable Supplies)
     * as specified in Section 5.0 of the enhancement proposal.
     */
    public function seedStandardCategories(): void
    {
        Gate::authorize('inventory_categories.create');

        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $defaults = [
            // 5.1 Durable Assets
            ['name' => 'Laboratory Hardware', 'code' => 'DUR-LAB', 'classification' => 'durable', 'description' => 'Microscopes, centrifuges, autoclaves, spectrophotometers, and diagnostic units.'],
            ['name' => 'Practical Training Equipment', 'code' => 'DUR-TRN', 'classification' => 'durable', 'description' => 'Anatomical models, patient simulation mannequins, and clinical/practical demonstration equipment.'],
            ['name' => 'ICT & Infrastructure', 'code' => 'DUR-ICT', 'classification' => 'durable', 'description' => 'Office workstations, lab computers, network switches, projectors, and printers.'],
            ['name' => 'Institutional Furniture', 'code' => 'DUR-FUR', 'classification' => 'durable', 'description' => 'Student lab benches, lecture hall seating, executive desks, and filing cabinets.'],
            // 5.2 Consumable Supplies
            ['name' => 'Medical & Clinical Disposables', 'code' => 'CON-MED', 'classification' => 'consumable', 'description' => 'Sterile surgical gloves, syringes, swabs, face masks, needles, and bandages.'],
            ['name' => 'Laboratory Reagents & Chemicals', 'code' => 'CON-REG', 'classification' => 'consumable', 'description' => 'Staining solutions, test reagents, chemicals, specimen containers, and culture media.'],
            ['name' => 'Apparatus & Glassware', 'code' => 'CON-GLS', 'classification' => 'consumable', 'description' => 'Test tubes, slides, coverslips, pipettes, and petri dishes managed as active stock.'],
            ['name' => 'Administrative Stationery', 'code' => 'CON-STA', 'classification' => 'consumable', 'description' => 'Exam answer booklets, continuous assessment forms, and general office paper.'],
        ];

        foreach ($defaults as $cat) {
            InventoryCategory::firstOrCreate(
                ['institution_id' => $instId, 'name' => $cat['name']],
                [
                    'code' => $cat['code'],
                    'classification' => $cat['classification'],
                    'description' => $cat['description'],
                    'status' => 'active',
                ]
            );
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Standard durable & consumable categories initialized for this institution.'),
        ]);
    }

    public function openCreateCategoryModal(): void
    {
        Gate::authorize('inventory_categories.create');

        $this->editingCategoryId = null;
        $this->cat_name = '';
        $this->cat_code = '';
        $this->cat_classification = 'durable';
        $this->cat_description = '';
        $this->cat_status = 'active';
        $this->showCategoryModal = true;
    }

    public function openEditCategoryModal(int $categoryId): void
    {
        Gate::authorize('inventory_categories.edit');

        $instId = $this->resolveInstitutionId();
        $category = InventoryCategory::where('institution_id', $instId)->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->cat_name = $category->name;
        $this->cat_code = $category->code ?? '';
        $this->cat_classification = $category->classification;
        $this->cat_description = $category->description ?? '';
        $this->cat_status = $category->status;
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        Gate::authorize($this->editingCategoryId ? 'inventory_categories.edit' : 'inventory_categories.create');

        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            abort(403, 'Institution context required.');
        }

        $validated = $this->validate([
            'cat_name' => ['required', 'string', 'max:191'],
            'cat_code' => ['nullable', 'string', 'max:50'],
            'cat_classification' => ['required', 'in:durable,consumable'],
            'cat_description' => ['nullable', 'string', 'max:1000'],
            'cat_status' => ['required', 'in:active,inactive'],
        ]);

        if ($this->editingCategoryId) {
            $category = InventoryCategory::where('institution_id', $instId)->findOrFail($this->editingCategoryId);
            $category->update([
                'name' => $validated['cat_name'],
                'code' => $validated['cat_code'] ?: null,
                'classification' => $validated['cat_classification'],
                'description' => $validated['cat_description'] ?: null,
                'status' => $validated['cat_status'],
            ]);
            $msg = __('Category updated successfully.');
        } else {
            InventoryCategory::create([
                'institution_id' => $instId,
                'name' => $validated['cat_name'],
                'code' => $validated['cat_code'] ?: null,
                'classification' => $validated['cat_classification'],
                'description' => $validated['cat_description'] ?: null,
                'status' => $validated['cat_status'],
            ]);
            $msg = __('Category created successfully.');
        }

        $this->showCategoryModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function deleteCategory(int $categoryId): void
    {
        Gate::authorize('inventory_categories.delete');

        $instId = $this->resolveInstitutionId();
        $category = InventoryCategory::where('institution_id', $instId)->findOrFail($categoryId);
        $category->delete();

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Category deleted.')]);
    }

    public function openCreateItemModal(): void
    {
        Gate::authorize('inventory_items.create');

        $this->editingItemId = null;
        $this->item_category_id = '';
        $this->item_name = '';
        $this->item_code = 'INV-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));
        $this->item_unit = 'Pieces';
        $this->item_min_threshold = 5;
        $this->item_description = '';
        $this->item_status = 'active';
        $this->showItemModal = true;
    }

    public function openEditItemModal(int $itemId): void
    {
        Gate::authorize('inventory_items.edit');

        $instId = $this->resolveInstitutionId();
        $item = InventoryItem::where('institution_id', $instId)->findOrFail($itemId);

        $this->editingItemId = $item->id;
        $this->item_category_id = $item->inventory_category_id;
        $this->item_name = $item->name;
        $this->item_code = $item->code;
        $this->item_unit = $item->unit_of_measure;
        $this->item_min_threshold = $item->min_stock_threshold;
        $this->item_description = $item->description ?? '';
        $this->item_status = $item->status;
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        Gate::authorize($this->editingItemId ? 'inventory_items.edit' : 'inventory_items.create');

        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            abort(403, 'Institution context required.');
        }

        $validated = $this->validate([
            'item_category_id' => [
                'required',
                Rule::exists('inventory_categories', 'id')->where('institution_id', $instId),
            ],
            'item_name' => ['required', 'string', 'max:191'],
            'item_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('inventory_items', 'code')
                    ->where('institution_id', $instId)
                    ->ignore($this->editingItemId),
            ],
            'item_unit' => ['required', 'string', 'max:50'],
            'item_min_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'item_description' => ['nullable', 'string', 'max:1000'],
            'item_status' => ['required', 'in:active,inactive'],
        ]);

        if ($this->editingItemId) {
            $item = InventoryItem::where('institution_id', $instId)->findOrFail($this->editingItemId);
            $item->update([
                'inventory_category_id' => $validated['item_category_id'],
                'name' => $validated['item_name'],
                'code' => strtoupper(trim($validated['item_code'])),
                'unit_of_measure' => $validated['item_unit'],
                'min_stock_threshold' => $validated['item_min_threshold'],
                'description' => $validated['item_description'] ?: null,
                'status' => $validated['item_status'],
            ]);
            $msg = __('Catalogue item updated successfully.');
        } else {
            InventoryItem::create([
                'institution_id' => $instId,
                'inventory_category_id' => $validated['item_category_id'],
                'name' => $validated['item_name'],
                'code' => strtoupper(trim($validated['item_code'])),
                'unit_of_measure' => $validated['item_unit'],
                'min_stock_threshold' => $validated['item_min_threshold'],
                'description' => $validated['item_description'] ?: null,
                'status' => $validated['item_status'],
            ]);
            $msg = __('Catalogue item created successfully.');
        }

        $this->showItemModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function deleteItem(int $itemId): void
    {
        Gate::authorize('inventory_items.delete');

        $instId = $this->resolveInstitutionId();
        $item = InventoryItem::where('institution_id', $instId)->findOrFail($itemId);
        $item->delete();

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Catalogue item deleted.')]);
    }

    public function openBreakdownModal(int $itemId): void
    {
        $this->breakdownItemId = $itemId;
        $this->showBreakdownModal = true;
    }

    public function with(): array
    {
        $instId = $this->resolveInstitutionId();

        $categories = $instId
            ? InventoryCategory::query()
                ->withCount('items')
                ->where('institution_id', $instId)
                ->when($this->classificationFilter, fn ($q) => $q->where('classification', $this->classificationFilter))
                ->orderBy('classification')
                ->orderBy('name')
                ->get()
            : collect();

        $itemsQuery = InventoryItem::query()
            ->with([
                'category',
                'stocks' => fn ($q) => $q->with('location')->whereHas('location', fn ($lq) => $lq->where('status', 'active')),
            ])
            ->where('institution_id', $instId ?? 0)
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->classificationFilter, function ($q) {
                $q->whereHas('category', fn ($cq) => $cq->where('classification', $this->classificationFilter));
            })
            ->when($this->categoryFilter, fn ($q) => $q->where('inventory_category_id', $this->categoryFilter))
            ->orderBy('name');

        if ($this->stockFilter === 'low_stock') {
            $itemsQuery->whereRaw('(
                SELECT COALESCE(SUM(inventory_stocks.quantity), 0)
                FROM inventory_stocks
                INNER JOIN inventory_locations ON inventory_locations.id = inventory_stocks.inventory_location_id
                WHERE inventory_stocks.inventory_item_id = inventory_items.id
                AND inventory_locations.status = ?
            ) < inventory_items.min_stock_threshold AND inventory_items.min_stock_threshold > 0', ['active']);
        } elseif ($this->stockFilter === 'out_of_stock') {
            $itemsQuery->whereRaw('(
                SELECT COALESCE(SUM(inventory_stocks.quantity), 0)
                FROM inventory_stocks
                INNER JOIN inventory_locations ON inventory_locations.id = inventory_stocks.inventory_location_id
                WHERE inventory_stocks.inventory_item_id = inventory_items.id
                AND inventory_locations.status = ?
            ) = 0', ['active']);
        }

        $breakdownItem = $this->breakdownItemId && $instId
            ? InventoryItem::with(['category', 'stocks.location'])->where('institution_id', $instId)->find($this->breakdownItemId)
            : null;

        return [
            'institutions' => auth()->user()->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'categories' => $categories,
            'allCategories' => $instId ? InventoryCategory::where('institution_id', $instId)->where('status', 'active')->orderBy('name')->get() : collect(),
            'items' => $itemsQuery->paginate(15),
            'breakdownItem' => $breakdownItem,
            'standardUnits' => ['Pieces', 'Units', 'Boxes', 'Packs', 'Bottles', 'Vials', 'Litres', 'Sets', 'Pairs', 'Rolls', 'Reams', 'Cartons'],
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Items & Categories') }}</flux:heading>
            <flux:subheading>{{ __('Manage inventory items, categories, and low-stock alert levels') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-60" :placeholder="__('Select Institution...')">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @can('inventory_categories.create')
                <flux:button variant="ghost" icon="tag" wire:click="openCreateCategoryModal" class="w-full sm:w-auto">
                    {{ __('New Category') }}
                </flux:button>
            @endcan

            @can('inventory_items.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateItemModal" class="w-full sm:w-auto">
                    {{ __('Add Item') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto">
        <button type="button" wire:click="$set('tab', 'items')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'items' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.clipboard-document-list class="size-4" />
            {{ __('Items') }}
        </button>

        <button type="button" wire:click="$set('tab', 'categories')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'categories' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.tag class="size-4" />
            {{ __('Categories') }}
            <flux:badge size="sm" color="zinc">{{ $categories->count() }}</flux:badge>
        </button>
    </div>

    {{-- TAB 1: ITEMS CATALOGUE --}}
    @if($tab === 'items')
        {{-- Responsive Filter Toolbar --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search item name or code...')" clearable />

            <flux:select wire:model.live="classificationFilter">
                <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
                <flux:select.option value="durable">{{ __('Durable Items') }}</flux:select.option>
                <flux:select.option value="consumable">{{ __('Consumables') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="categoryFilter">
                <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
                @foreach($allCategories as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->name }} ({{ ucfirst($cat->classification) }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="stockFilter">
                <flux:select.option value="">{{ __('All Stock Levels') }}</flux:select.option>
                <flux:select.option value="low_stock">{{ __('Low Stock') }}</flux:select.option>
                <flux:select.option value="out_of_stock">{{ __('Out of Stock (0)') }}</flux:select.option>
            </flux:select>
        </div>

        {{-- Desktop Table View (md and up) --}}
        <div class="hidden md:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Item / Code') }}</th>
                        <th class="px-4 py-3">{{ __('Type & Category') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Unit') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Min Level') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Total Stock') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                    @forelse($items as $item)
                        @php
                            $totalStock = (int) $item->stocks->sum('quantity');
                            $isLow = $item->isBelowThreshold($totalStock);
                        @endphp
                        <tr wire:key="item-row-{{ $item->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-900/20 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                <div class="text-xs font-mono text-zinc-500">{{ $item->code }}</div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$item->category?->classification === 'durable' ? 'indigo' : 'teal'">
                                        {{ $item->category?->classification === 'durable' ? __('Durable') : __('Consumable') }}
                                    </flux:badge>
                                </div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $item->category?->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-center text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $item->unit_of_measure }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ number_format($item->min_stock_threshold) }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <button type="button" wire:click="openBreakdownModal({{ $item->id }})"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-bold transition-colors {{ $isLow ? 'bg-red-50 border-red-200 text-red-700 dark:bg-red-950/50 dark:border-red-800 dark:text-red-300' : 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-950/50 dark:border-emerald-800 dark:text-emerald-300' }}">
                                    <span>{{ number_format($totalStock) }}</span>
                                    @if($isLow)
                                        <flux:icon.exclamation-triangle class="size-3.5 text-red-600 dark:text-red-400" />
                                    @endif
                                </button>
                                <div class="text-[10px] text-zinc-400 mt-0.5">{{ $item->stocks->count() }} {{ Str::plural('room', $item->stocks->count()) }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($isLow)
                                    <flux:badge size="sm" color="amber">{{ __('Low Stock') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" :color="$item->status === 'active' ? 'green' : 'zinc'">
                                        {{ ucfirst($item->status) }}
                                    </flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="xs" variant="ghost" icon="eye" wire:click="openBreakdownModal({{ $item->id }})" title="{{ __('Room Breakdown') }}" />
                                    @can('inventory_items.edit')
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditItemModal({{ $item->id }})" title="{{ __('Edit Item') }}" />
                                    @endcan
                                    @can('inventory_items.delete')
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('Delete this catalogue item and all its room balances?') }}" title="{{ __('Delete') }}" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No inventory catalogue items match your current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Stacked Cards View (< md) --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($items as $item)
                @php
                    $totalStock = (int) $item->stocks->sum('quantity');
                    $isLow = $item->isBelowThreshold($totalStock);
                @endphp
                <div wire:key="item-card-{{ $item->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 shadow-xs space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                            <div class="text-xs font-mono text-zinc-500 mt-0.5">{{ $item->code }} &bull; {{ $item->category?->name }}</div>
                        </div>
                        <flux:badge size="sm" :color="$item->category?->classification === 'durable' ? 'indigo' : 'teal'">
                            {{ $item->category?->classification === 'durable' ? __('Durable') : __('Consumable') }}
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-700/60 text-xs">
                        <div>
                            <span class="text-zinc-500 block">{{ __('Total Stock') }}</span>
                            <span class="font-bold text-sm {{ $isLow ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format($totalStock) }} {{ $item->unit_of_measure }}
                            </span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block">{{ __('Min Level') }}</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ number_format($item->min_stock_threshold) }} {{ $item->unit_of_measure }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-700/60">
                        <flux:button size="xs" variant="ghost" icon="eye" wire:click="openBreakdownModal({{ $item->id }})">
                            {{ __('Room Balances') }} ({{ $item->stocks->count() }})
                        </flux:button>
                        <div class="flex items-center gap-1">
                            @can('inventory_items.edit')
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditItemModal({{ $item->id }})" />
                            @endcan
                            @can('inventory_items.delete')
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('Delete this item?') }}" />
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                    {{ __('No catalogue items found.') }}
                </div>
            @endforelse
        </div>

        <div>{{ $items->links() }}</div>
    @endif

    {{-- TAB 2: CATEGORIES & CLASSIFICATION FRAMEWORK (INV-02) --}}
    @if($tab === 'categories')
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Item Categories') }}</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Group items into Durable (equipment, furniture, hardware) and Consumable (supplies, reagents, stationery).') }}
                </p>
            </div>
            @can('inventory_categories.create')
                <flux:button size="sm" variant="filled" icon="sparkles" wire:click="seedStandardCategories">
                    {{ __('Load Default Categories') }}
                </flux:button>
            @endcan
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($categories as $cat)
                <div wire:key="cat-card-{{ $cat->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 shadow-xs flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                    <span>{{ $cat->name }}</span>
                                    @if($cat->code)
                                        <span class="text-xs font-mono px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">{{ $cat->code }}</span>
                                    @endif
                                </div>
                            </div>
                            <flux:badge size="sm" :color="$cat->classification === 'durable' ? 'indigo' : 'teal'">
                                {{ $cat->classification === 'durable' ? __('Durable') : __('Consumable') }}
                            </flux:badge>
                        </div>
                        @if($cat->description)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2">{{ $cat->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-700/60">
                        <span class="text-xs font-medium text-zinc-500">
                            {{ $cat->items_count }} {{ Str::plural('catalogue item', $cat->items_count) }}
                        </span>
                        <div class="flex items-center gap-1">
                            @can('inventory_categories.edit')
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditCategoryModal({{ $cat->id }})" />
                            @endcan
                            @can('inventory_categories.delete')
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteCategory({{ $cat->id }})" wire:confirm="{{ __('Delete this category?') }}" />
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center text-zinc-500">
                    <p class="mb-3">{{ __('No categories defined yet for this institution.') }}</p>
                    @can('inventory_categories.create')
                        <flux:button size="sm" variant="primary" wire:click="seedStandardCategories">
                            {{ __('Load Default Categories') }}
                        </flux:button>
                    @endcan
                </div>
            @endforelse
        </div>
    @endif

    {{-- Create/Edit Item Modal --}}
    <flux:modal wire:model.self="showItemModal" class="max-w-lg w-full">
        <form wire:submit="saveItem" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingItemId ? __('Edit Item') : __('Add Item') }}</flux:heading>
                <flux:subheading>{{ __('Set item details and minimum stock alert level.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:select wire:model="item_category_id" :label="__('Category')" required>
                        <flux:select.option value="">{{ __('Select category...') }}</flux:select.option>
                        @foreach($allCategories as $cat)
                            <flux:select.option :value="$cat->id">
                                {{ $cat->name }} ({{ $cat->classification === 'durable' ? __('Durable') : __('Consumable') }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="sm:col-span-2">
                    <flux:input wire:model="item_name" :label="__('Item Name')" :placeholder="__('e.g. Binocular Light Microscope / Sterile Latex Gloves')" required />
                </div>

                <flux:input wire:model="item_code" :label="__('Item Code')" :placeholder="__('e.g. INV-MIC01')" required />

                <flux:input wire:model="item_unit" :label="__('Unit of Measurement')" list="standard-units-list" :placeholder="__('Pieces, Boxes, Litres...')" required />
                <datalist id="standard-units-list">
                    @foreach($standardUnits as $u)
                        <option value="{{ $u }}"></option>
                    @endforeach
                </datalist>

                <flux:input type="number" min="0" wire:model="item_min_threshold" :label="__('Minimum Stock Level')" required />

                <flux:select wire:model="item_status" :label="__('Status')">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="item_description" :label="__('Specification / Notes')" rows="2" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showItemModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Item') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Create/Edit Category Modal --}}
    <flux:modal wire:model.self="showCategoryModal" class="max-w-lg w-full">
        <form wire:submit="saveCategory" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingCategoryId ? __('Edit Category') : __('New Category') }}</flux:heading>
                <flux:subheading>{{ __('Group items under Durable or Consumable.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <flux:input wire:model="cat_name" :label="__('Category Name')" :placeholder="__('e.g. Laboratory Hardware')" required />
                </div>

                <flux:input wire:model="cat_code" :label="__('Short Code')" :placeholder="__('e.g. DUR-LAB')" />

                <flux:select wire:model="cat_classification" :label="__('Type')" required>
                    <flux:select.option value="durable">{{ __('Durable (Long-term)') }}</flux:select.option>
                    <flux:select.option value="consumable">{{ __('Consumable (Depletable)') }}</flux:select.option>
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="cat_description" :label="__('Description')" rows="2" />
                </div>

                <div class="sm:col-span-2">
                    <flux:select wire:model="cat_status" :label="__('Status')">
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showCategoryModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Category') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Room Stock Breakdown Modal --}}
    <flux:modal wire:model.self="showBreakdownModal" class="max-w-lg w-full">
        @if($breakdownItem)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $breakdownItem->name }}</flux:heading>
                    <flux:subheading>{{ $breakdownItem->code }} &bull; {{ $breakdownItem->category?->name }}</flux:subheading>
                </div>

                <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-xs">
                    <span>{{ __('Total Stock:') }} <strong>{{ number_format($breakdownItem->stocks->sum('quantity')) }} {{ $breakdownItem->unit_of_measure }}</strong></span>
                    <span>{{ __('Min Level:') }} <strong>{{ number_format($breakdownItem->min_stock_threshold) }} {{ $breakdownItem->unit_of_measure }}</strong></span>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700 max-h-72 overflow-y-auto">
                    @forelse($breakdownItem->stocks as $stock)
                        <div class="py-2.5 flex items-center justify-between text-sm">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $stock->location?->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $stock->location?->location_type_label }}</div>
                            </div>
                            <div class="font-bold text-zinc-900 dark:text-white">
                                {{ number_format($stock->quantity) }} {{ $breakdownItem->unit_of_measure }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-zinc-500">
                            {{ __('No room stock balances recorded for this item yet.') }}
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="primary" type="button" wire:click="$set('showBreakdownModal', false)">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

