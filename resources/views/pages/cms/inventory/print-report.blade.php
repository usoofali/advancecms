<?php

use App\Models\Institution;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Stock Sheet')] class extends Component
{
    public ?int $institution_id = null;

    public ?int $location_id = null;

    public string $classification = '';

    public ?int $category_id = null;

    public string $stock_filter = '';

    public function mount(): void
    {
        Gate::authorize('inventory.reports');

        $user = auth()->user();
        $reqInstId = request()->query('institution_id') ? (int) request()->query('institution_id') : null;
        $this->institution_id = $user->hasRole('Super Admin')
            ? ($reqInstId ?: Institution::where('status', 'active')->value('id'))
            : $user->institution_id;

        $this->location_id = request()->query('location_id') ? (int) request()->query('location_id') : null;
        $this->classification = request()->query('classification', '');
        $this->category_id = request()->query('category_id') ? (int) request()->query('category_id') : null;
        $this->stock_filter = request()->query('stock_filter', '');

        if ($this->location_id) {
            $loc = InventoryLocation::where('institution_id', $this->institution_id)->findOrFail($this->location_id);
            if (! $loc->isAccessibleBy($user)) {
                abort(403, 'You do not have permission to print reports for this location.');
            }
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $institution = Institution::find($this->institution_id);
        $location = $this->location_id ? InventoryLocation::with('locatable')->find($this->location_id) : null;
        $category = $this->category_id ? InventoryCategory::find($this->category_id) : null;

        $accessibleLocationIds = InventoryLocation::query()
            ->accessibleBy($user, $this->institution_id)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $targetLocationIds = $this->location_id
            ? [$this->location_id]
            : $accessibleLocationIds;

        $items = InventoryItem::query()
            ->with([
                'category',
                'stocks' => fn ($q) => $q->with('location')->whereIn('inventory_location_id', $targetLocationIds),
            ])
            ->where('institution_id', $this->institution_id ?? 0)
            ->where('status', 'active')
            ->when($this->classification, fn ($q) => $q->whereHas('category', fn ($cq) => $cq->where('classification', $this->classification)))
            ->when($this->category_id, fn ($q) => $q->where('inventory_category_id', $this->category_id))
            ->orderBy('name')
            ->get()
            ->filter(function (InventoryItem $item) {
                if ($this->stock_filter === 'low_stock') {
                    return $item->isBelowThreshold((int) $item->stocks->sum('quantity'));
                }
                if ($this->stock_filter === 'zero_stock') {
                    return (int) $item->stocks->sum('quantity') === 0;
                }

                return true;
            })
            ->values();

        return compact('institution', 'location', 'category', 'items');
    }
}; ?>

<div class="max-w-5xl mx-auto p-6 sm:p-8 bg-white text-gray-900 min-h-screen">
    {{-- Print Action Bar --}}
    <div class="flex items-center justify-between mb-6 print:hidden">
        <a href="{{ route('cms.inventory.reports') }}" class="text-xs font-semibold text-gray-600 hover:text-gray-900">&larr; Back to Stock Reports</a>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-lg hover:bg-gray-800 cursor-pointer">
            Print Stock Sheet
        </button>
    </div>

    {{-- Header --}}
    <div class="text-center border-b border-gray-300 pb-5 mb-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide">{{ $institution?->name ?? config('app.name') }}</h1>
        <h2 class="text-base font-semibold text-gray-700 mt-1">
            {{ $location ? 'ROOM STOCK SHEET' : 'INVENTORY STOCK REPORT' }}
        </h2>
        <p class="text-xs text-gray-500 mt-1">Generated: {{ now()->format('F d, Y h:i A') }} &bull; Printed by: {{ auth()->user()->name }}</p>
    </div>

    {{-- Scope Metadata --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
        <div>
            <span class="font-bold block text-gray-500 uppercase">Location</span>
            <span class="font-semibold">{{ $location ? $location->name : 'All Rooms' }}</span>
        </div>
        <div>
            <span class="font-bold block text-gray-500 uppercase">Type</span>
            <span class="font-semibold">{{ $classification ? ucfirst($classification) : 'Durable & Consumable' }}</span>
        </div>
        <div>
            <span class="font-bold block text-gray-500 uppercase">Category</span>
            <span class="font-semibold">{{ $category ? $category->name : 'All Categories' }}</span>
        </div>
        <div>
            <span class="font-bold block text-gray-500 uppercase">Stock Filter</span>
            <span class="font-semibold">{{ $stock_filter ? str_replace('_', ' ', ucfirst($stock_filter)) : 'All Items' }}</span>
        </div>
    </div>

    {{-- Data Table --}}
    <table class="w-full text-xs border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-100 text-left font-bold border-b border-gray-300">
                <th class="p-2 border border-gray-300">#</th>
                <th class="p-2 border border-gray-300">Code</th>
                <th class="p-2 border border-gray-300">Item Name</th>
                <th class="p-2 border border-gray-300">Category / Type</th>
                <th class="p-2 border border-gray-300 text-center">Unit</th>
                <th class="p-2 border border-gray-300 text-center">Min Level</th>
                <th class="p-2 border border-gray-300 text-center">Recorded Qty</th>
                <th class="p-2 border border-gray-300 text-center">Counted Qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                @php
                    $qty = (int) $item->stocks->sum('quantity');
                    $isLow = $item->isBelowThreshold($qty);
                @endphp
                <tr class="border-b border-gray-200">
                    <td class="px-2.5 py-2 border border-gray-300">{{ $index + 1 }}</td>
                    <td class="px-2.5 py-2 border border-gray-300 font-mono">{{ $item->code }}</td>
                    <td class="px-2.5 py-2 border border-gray-300 font-semibold">
                        {{ $item->name }}
                        @if($isLow)
                            <span class="ml-1 text-[10px] font-bold text-red-700">[LOW]</span>
                        @endif
                    </td>
                    <td class="px-2.5 py-2 border border-gray-300">
                        {{ $item->category?->name }} ({{ ucfirst($item->category?->classification ?? '') }})
                    </td>
                    <td class="px-2.5 py-2 border border-gray-300 text-center">{{ $item->unit_of_measure }}</td>
                    <td class="px-2.5 py-2 border border-gray-300 text-center">{{ number_format($item->min_stock_threshold) }}</td>
                    <td class="px-2.5 py-2 border border-gray-300 text-center font-bold">{{ number_format($qty) }}</td>
                    <td class="px-2.5 py-2 border border-gray-300 text-center">___________</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="p-6 text-center text-gray-500">No inventory items match this report criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Sign-off Footer --}}
    <div class="mt-14 pt-6 border-t border-gray-300 grid grid-cols-1 sm:grid-cols-3 gap-8 text-xs text-gray-700">
        <div class="text-center">
            <div class="border-b border-gray-400 mb-2 h-10"></div>
            <p class="font-bold">Assigned Officer</p>
            <p class="text-[11px] text-gray-500">Signature &amp; Date</p>
        </div>
        <div class="text-center">
            <div class="border-b border-gray-400 mb-2 h-10"></div>
            <p class="font-bold">Head of Unit / Store</p>
            <p class="text-[11px] text-gray-500">Signature &amp; Date</p>
        </div>
        <div class="text-center">
            <div class="border-b border-gray-400 mb-2 h-10"></div>
            <p class="font-bold">Head of Administration</p>
            <p class="text-[11px] text-gray-500">Signature &amp; Date</p>
        </div>
    </div>
</div>

