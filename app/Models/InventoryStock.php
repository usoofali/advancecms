<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\InventoryStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStock extends Model
{
    /** @use HasFactory<InventoryStockFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'inventory_item_id',
        'inventory_location_id',
        'quantity',
        'min_threshold_override',
        'last_counted_at',
        'last_counted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_threshold_override' => 'integer',
            'last_counted_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function lastCountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_counted_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryStockAdjustment::class);
    }

    public function getActivityModule(): string
    {
        return 'Inventory';
    }

    public function getActivityLogLabel(): string
    {
        $itemName = $this->item?->name ?? "Item #{$this->inventory_item_id}";
        $locationName = $this->location?->name ?? "Location #{$this->inventory_location_id}";

        return "Stock: {$itemName} @ {$locationName}";
    }
}
