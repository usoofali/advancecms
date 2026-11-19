<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffLoan extends Model
{
    protected $fillable = [
        'institution_id',
        'staff_id',
        'title',
        'reference_no',
        'principal_amount',
        'monthly_deduction',
        'total_repaid',
        'remaining_balance',
        'start_month',
        'start_year',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'monthly_deduction' => 'decimal:2',
            'total_repaid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'start_month' => 'integer',
            'start_year' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(StaffLoanRepayment::class)->orderByDesc('paid_at');
    }

    /**
     * Scope active loans eligible for deduction in the given payroll month/year.
     */
    public function scopeActiveForPeriod(Builder $query, int $month, int $year): Builder
    {
        return $query
            ->where('status', 'active')
            ->where('remaining_balance', '>', 0)
            ->where(function (Builder $q) use ($month, $year): void {
                $q->where('start_year', '<', $year)
                    ->orWhere(function (Builder $sq) use ($month, $year): void {
                        $sq->where('start_year', $year)
                            ->where('start_month', '<=', $month);
                    });
            });
    }

    /**
     * Scope open loans (active or paused) that count toward the concurrent loan limit.
     */
    public function scopeOpenForStaff(Builder $query, int $staffId): Builder
    {
        return $query
            ->where('staff_id', $staffId)
            ->whereIn('status', ['active', 'paused']);
    }

    /**
     * Compute the scheduled installment for this period (never exceeding remaining balance).
     */
    public function dueInstallmentAmount(): float
    {
        if ($this->status !== 'active' || (float) $this->remaining_balance <= 0) {
            return 0.0;
        }

        return round(min((float) $this->monthly_deduction, (float) $this->remaining_balance), 2);
    }
}
