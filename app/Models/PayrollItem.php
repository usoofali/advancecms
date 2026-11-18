<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'institution_id',
        'staff_id',
        'payroll_category_id',
        'department_id',
        'pay_type',
        'base_pay',
        'contacts_count',
        'is_contact_overridden',
        'credit_units_total',
        'hours_total',
        'rate_used',
        'allowances_breakdown',
        'total_allowances',
        'deductions_breakdown',
        'total_deductions',
        'manual_adjustment',
        'adjustment_note',
        'gross_pay',
        'net_pay',
        'bank_name',
        'account_number',
        'account_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_contact_overridden' => 'boolean',
            'base_pay' => 'decimal:2',
            'credit_units_total' => 'decimal:2',
            'hours_total' => 'decimal:2',
            'rate_used' => 'decimal:2',
            'allowances_breakdown' => 'array',
            'total_allowances' => 'decimal:2',
            'deductions_breakdown' => 'array',
            'total_deductions' => 'decimal:2',
            'manual_adjustment' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
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
}
