<?php

namespace App\Models;

use App\Traits\HasRoleScopes;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory, HasRoleScopes;

    protected $fillable = [
        'name',
        'default_allowance',
        'max_concurrent_staff_loans',
        'slug',
        'address',
        'email',
        'phone',
        'website',
        'logo_path',
        'acronym',
        'portal_url',
        'status',
        'is_admission_open',
        'admission_start_date',
        'admission_end_date',
        'established_year',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'max_concurrent_staff_loans' => 'integer',
            'is_admission_open' => 'boolean',
            'admission_start_date' => 'datetime',
            'admission_end_date' => 'datetime',
            'default_allowance' => 'decimal:2',
        ];
    }

    public function isAdmissionActive(): bool
    {
        return $this->getAdmissionStatusReason() === 'active';
    }

    public function getAdmissionStatusReason(): string
    {
        if (! $this->is_admission_open) {
            return 'manual_off';
        }

        $now = now();

        if ($this->admission_start_date && $now->lt($this->admission_start_date)) {
            return 'not_started';
        }

        if ($this->admission_end_date && $now->gt($this->admission_end_date)) {
            return 'expired';
        }

        return 'active';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendancePayments(): HasMany
    {
        return $this->hasMany(AttendancePayment::class);
    }

    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function payrollCategories(): HasMany
    {
        return $this->hasMany(PayrollCategory::class);
    }

    public function payrollStaffProfiles(): HasMany
    {
        return $this->hasMany(PayrollStaffProfile::class);
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(PayrollComponent::class);
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
