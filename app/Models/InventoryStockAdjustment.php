<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockAdjustment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'inventory_stock_id',
        'inventory_item_id',
        'inventory_location_id',
        'previous_quantity',
        'new_quantity',
        'variance',
        'adjustment_type',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
            'variance' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function adjustmentTypeLabels(): array
    {
        return [
            'recount' => 'Physical Count',
            'initial_stock' => 'Opening Stock',
            'replenishment' => 'Restock / Received',
            'consumption' => 'Used / Issued',
            'damage_writeoff' => 'Damaged / Expired',
        ];
    }

    public function getAdjustmentTypeLabelAttribute(): string
    {
        return self::adjustmentTypeLabels()[$this->adjustment_type] ?? ucfirst(str_replace('_', ' ', $this->adjustment_type));
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivityModule(): string
    {
        return 'Inventory';
    }

    public function getActivityLogLabel(): string
    {
        $itemName = $this->item?->name ?? "Item #{$this->inventory_item_id}";
        $locationName = $this->location?->name ?? "Location #{$this->inventory_location_id}";

        return "Stock Adjustment: {$itemName} @ {$locationName} ({$this->previous_quantity} -> {$this->new_quantity})";
    }
}
