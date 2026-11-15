<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\InventoryCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    /** @use HasFactory<InventoryCategoryFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'name',
        'code',
        'classification',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => 'string',
            'status' => 'string',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function scopeForInstitution(Builder $query, ?int $institutionId): Builder
    {
        if (! $institutionId) {
            return $query;
        }

        return $query->where('institution_id', $institutionId);
    }

    public function scopeDurable(Builder $query): Builder
    {
        return $query->where('classification', 'durable');
    }

    public function scopeConsumable(Builder $query): Builder
    {
        return $query->where('classification', 'consumable');
    }

    public function getActivityModule(): string
    {
        return 'Inventory';
    }

    public function getActivityLogLabel(): string
    {
        return "Category: {$this->name}";
    }
}
