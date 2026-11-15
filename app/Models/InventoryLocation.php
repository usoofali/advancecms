<?php

namespace App\Models;

use App\Traits\HasRoleScopes;
use App\Traits\LogsActivity;
use Database\Factories\InventoryLocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryLocation extends Model
{
    /** @use HasFactory<InventoryLocationFactory> */
    use HasFactory, HasRoleScopes, LogsActivity;

    protected $fillable = [
        'institution_id',
        'name',
        'code',
        'location_type',
        'locatable_type',
        'locatable_id',
        'building_or_block',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location_type' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * Standard location type labels.
     *
     * @return array<string, string>
     */
    public static function locationTypeLabels(): array
    {
        return [
            'lab_clinic' => 'Clinical & Practical Lab',
            'store_warehouse' => 'Store & Warehousing',
            'office_hall' => 'Office & Lecture Hall',
            'workshop' => 'Technical Workshop',
            'other' => 'Other Facility',
        ];
    }

    public function getLocationTypeLabelAttribute(): string
    {
        return self::locationTypeLabels()[$this->location_type] ?? ucfirst(str_replace('_', ' ', $this->location_type));
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function locatable(): MorphTo
    {
        return $this->morphTo();
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
     * Filter locations strictly to those accessible by the given user.
     * Enforces multi-tenancy and custodial role separation (INV-06).
     */
    public function scopeAccessibleBy(Builder $query, User $user, ?int $institutionId = null): Builder
    {
        $effectiveInstitutionId = $institutionId ?? $user->institution_id;

        if ($effectiveInstitutionId) {
            $query->where('inventory_locations.institution_id', $effectiveInstitutionId);
        }

        // System / Institutional Admins and Auditors with view_all can access all locations in the institution
        if (
            $user->hasRole('Super Admin') ||
            $user->hasRole('Institutional Admin') ||
            $user->hasRole('Inventory Auditor') ||
            $user->hasPermissionTo('inventory.view_all')
        ) {
            return $query;
        }

        // Gather direct location assignments for Inventory Officer
        $scopedLocationIds = $user->getScopedModelIds('Inventory Officer', self::class);

        // Gather departmental scopes (Inventory Officer or HOD on a Department)
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Inventory Officer', Department::class),
            $user->getScopedModelIds('Head of Department (HOD)', Department::class)
        ));

        // Legacy HOD fallback via staff->hodDepartments
        if ($user->staff) {
            $hodDeptIds = Department::where('hod_id', $user->staff->id)->pluck('id')->toArray();
            $scopedDeptIds = array_unique(array_merge($scopedDeptIds, $hodDeptIds));
        }

        return $query->where(function (Builder $sub) use ($scopedLocationIds, $scopedDeptIds) {
            $hasCondition = false;

            if (! empty($scopedLocationIds)) {
                $sub->orWhereIn('inventory_locations.id', $scopedLocationIds);
                $hasCondition = true;
            }

            if (! empty($scopedDeptIds)) {
                $sub->orWhere(function (Builder $deptQuery) use ($scopedDeptIds) {
                    $deptQuery->where('inventory_locations.locatable_type', (new Department)->getMorphClass())
                        ->whereIn('inventory_locations.locatable_id', $scopedDeptIds);
                });
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $sub->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Determine whether a specific user has custody/reconciliation rights over this location.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $user->hasRole('Super Admin') && $user->institution_id && $this->institution_id !== $user->institution_id) {
            return false;
        }

        return self::query()
            ->accessibleBy($user, $this->institution_id)
            ->where('inventory_locations.id', $this->id)
            ->exists();
    }

    public function getActivityModule(): string
    {
        return 'Inventory';
    }

    public function getActivityLogLabel(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : (string) $this->name;
    }
}
