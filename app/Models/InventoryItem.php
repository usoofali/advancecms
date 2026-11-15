<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            if (empty($item->code)) {
                $prefix = 'INV';
                $random = strtoupper(Str::random(6));
                $item->code = "{$prefix}-{$random}";
            }
        });
    }

    protected $fillable = [
        'institution_id',
        'inventory_category_id',
        'name',
        'code',
        'unit_of_measure',
        'min_stock_threshold',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_stock_threshold' => 'integer',
            'status' => 'string',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryStockAdjustment::class);
    }

    public function scopeForInstitution(Builder $query, ?int $institutionId): Builder
    {
        if (! $institutionId) {
            return $query;
        }

        return $query->where('institution_id', $institutionId);
    }

    /**
     * Get the total quantity across all active locations.
     */
    public function totalStockQuantity(): int
    {
        if ($this->relationLoaded('stocks')) {
            return (int) $this->stocks->sum('quantity');
        }

        return (int) $this->stocks()
            ->whereHas('location', fn (Builder $q) => $q->where('status', 'active'))
            ->sum('quantity');
    }

    /**
     * Determine whether cumulative stock has fallen below the minimum safe threshold.
     */
    public function isBelowThreshold(?int $totalQuantity = null): bool
    {
        if ($this->min_stock_threshold <= 0) {
            return false;
        }

        $total = $totalQuantity ?? $this->totalStockQuantity();

        return $total < $this->min_stock_threshold;
    }

    public function getActivityModule(): string
    {
        return 'Inventory';
    }

    public function getActivityLogLabel(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
