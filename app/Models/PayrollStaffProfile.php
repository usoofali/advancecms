<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\PayrollStaffProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollStaffProfile extends Model
{
    /** @use HasFactory<PayrollStaffProfileFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'staff_id',
        'payroll_category_id',
        'department_id',
        'pay_type',
        'contact_formula',
        'base_salary',
        'custom_rate',
        'bank_name',
        'account_number',
        'account_name',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'custom_rate' => 'decimal:2',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PayrollCategory::class, 'payroll_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Resolve effective pay type: Staff override -> Category -> Institution default ('fixed').
     */
    public function effectivePayType(?PayrollCategory $defaultCategory = null): string
    {
        return $this->pay_type
            ?? $this->category?->pay_type
            ?? $defaultCategory?->pay_type
            ?? 'fixed';
    }

    /**
     * Resolve effective contact formula: Staff override -> Category -> Institution default.
     */
    public function effectiveContactFormula(?PayrollCategory $defaultCategory = null): string
    {
        return $this->contact_formula
            ?? $this->category?->contact_formula
            ?? $defaultCategory?->contact_formula
            ?? 'contact_x_credit_unit';
    }

    /**
     * Resolve effective contact/hour rate: Staff custom_rate -> Category default_rate -> Institution default.
     */
    public function effectiveRate(?PayrollCategory $defaultCategory = null): float
    {
        if ($this->custom_rate !== null) {
            return (float) $this->custom_rate;
        }

        if ($this->category !== null) {
            return (float) $this->category->default_rate;
        }

        if ($defaultCategory !== null) {
            return (float) $defaultCategory->default_rate;
        }

        return (float) ($this->staff?->attendance_allowance ?? 0);
    }

    public function hasCustomOverride(): bool
    {
        return $this->pay_type !== null
            || $this->contact_formula !== null
            || $this->custom_rate !== null;
    }

    public function getActivityModule(): string
    {
        return 'Payroll';
    }

    public function getActivityLogLabel(): string
    {
        $staff = $this->staff;

        return $staff ? trim("{$staff->first_name} {$staff->last_name}") : "Staff Profile #{$this->id}";
    }
}
