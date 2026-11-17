<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollStaffProfile;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    /**
     * Generate or recalculate a draft payroll run for an institution and period.
     */
    public function generateOrRecalculateRun(
        Institution $institution,
        int $month,
        int $year,
        User $actor,
        ?string $title = null
    ): PayrollRun {
        return DB::transaction(function () use ($institution, $month, $year, $actor, $title): PayrollRun {
            $defaultTitle = Carbon::createFromDate($year, $month, 1)->format('F Y').' Payroll';

            $run = PayrollRun::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'title' => $title ?: $defaultTitle,
                    'status' => 'draft',
                    'prepared_by' => $actor->id,
                    'prepared_at' => now(),
                ]
            );

            if ($title && $run->status === 'draft') {
                $run->title = $title;
            }

            // Preserve any manual adjustments already entered on this draft run
            $existingAdjustments = $run->items()
                ->get()
                ->keyBy('staff_id')
                ->map(fn (PayrollItem $item) => [
                    'manual_adjustment' => (float) $item->manual_adjustment,
                    'adjustment_note' => $item->adjustment_note,
                ]);

            $defaultCategory = PayrollCategory::query()
                ->where('institution_id', $institution->id)
                ->where('is_default', true)
                ->where('status', 'active')
                ->first();

            $activeComponents = PayrollComponent::query()
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->get();

            $staffList = Staff::query()
                ->with(['payrollProfile.category', 'user'])
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get();

            $run->items()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;

            foreach ($staffList as $staff) {
                $profile = $staff->payrollProfile;

                // Skip staff with explicitly suspended payroll profile
                if ($profile && $profile->status === 'suspended') {
                    continue;
                }

                // Auto-create a lightweight profile only in memory if none exists yet
                $effectiveProfile = $profile ?? new PayrollStaffProfile([
                    'institution_id' => $institution->id,
                    'staff_id' => $staff->id,
                    'payroll_category_id' => $defaultCategory?->id,
                    'base_salary' => 0,
                ]);

                if (! $profile && $defaultCategory) {
                    $effectiveProfile->setRelation('category', $defaultCategory);
                }
                $effectiveProfile->setRelation('staff', $staff);

                $payType = $effectiveProfile->effectivePayType($defaultCategory);
                $formula = $effectiveProfile->effectiveContactFormula($defaultCategory);
                $rateUsed = $effectiveProfile->effectiveRate($defaultCategory);

                $contactsCount = 0;
                $creditUnitsTotal = 0.0;
                $hoursTotal = 0.0;
                $basePay = 0.0;

                if ($payType === 'fixed') {
                    $basePay = (float) $effectiveProfile->base_salary;
                } else {
                    $contactStats = $this->computeContactMetrics($staff, $institution->id, $month, $year);
                    $contactsCount = $contactStats['contacts_count'];
                    $creditUnitsTotal = $contactStats['credit_units_total'];
                    $hoursTotal = $contactStats['hours_total'];

                    $basePay = match ($formula) {
                        'contact_x_credit_unit' => $contactStats['weighted_contact_units'] * $rateUsed,
                        'hour_x_credit_unit' => $contactStats['weighted_hour_units'] * $rateUsed,
                        'per_hour' => $hoursTotal * $rateUsed,
                        default => $contactsCount * $rateUsed, // per_contact
                    };
                }

                $basePay = round($basePay, 2);

                // Resolve applicable allowances and deductions
                $matchingComponents = $this->filterApplicableComponents(
                    $activeComponents,
                    $staff,
                    $effectiveProfile->payroll_category_id,
                    $effectiveProfile->department_id
                );

                $allowancesBreakdown = [];
                $itemAllowancesTotal = 0.0;

                $deductionsBreakdown = [];
                $itemDeductionsTotal = 0.0;

                foreach ($matchingComponents as $comp) {
                    $amount = $comp->calculation_type === 'percentage'
                        ? round($basePay * ((float) $comp->amount_or_rate / 100), 2)
                        : round((float) $comp->amount_or_rate, 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    if ($comp->type === 'allowance') {
                        $allowancesBreakdown[] = ['name' => $comp->name, 'amount' => $amount];
                        $itemAllowancesTotal += $amount;
                    } else {
                        $deductionsBreakdown[] = ['name' => $comp->name, 'amount' => $amount];
                        $itemDeductionsTotal += $amount;
                    }
                }

                $adj = $existingAdjustments->get($staff->id);
                $manualAdjustment = $adj ? (float) $adj['manual_adjustment'] : 0.0;
                $adjustmentNote = $adj ? $adj['adjustment_note'] : null;

                $grossPay = round($basePay + $itemAllowancesTotal + max(0, $manualAdjustment), 2);
                $effectiveDeductions = round($itemDeductionsTotal + ($manualAdjustment < 0 ? abs($manualAdjustment) : 0), 2);
                $netPay = round(max(0, $grossPay - $effectiveDeductions), 2);

                PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'institution_id' => $institution->id,
                    'staff_id' => $staff->id,
                    'payroll_category_id' => $effectiveProfile->payroll_category_id,
                    'department_id' => $effectiveProfile->department_id,
                    'pay_type' => $payType,
                    'base_pay' => $basePay,
                    'contacts_count' => $contactsCount,
                    'credit_units_total' => round($creditUnitsTotal, 2),
                    'hours_total' => round($hoursTotal, 2),
                    'rate_used' => $rateUsed,
                    'allowances_breakdown' => $allowancesBreakdown,
                    'total_allowances' => round($itemAllowancesTotal, 2),
                    'deductions_breakdown' => $deductionsBreakdown,
                    'total_deductions' => $effectiveDeductions,
                    'manual_adjustment' => $manualAdjustment,
                    'adjustment_note' => $adjustmentNote,
                    'gross_pay' => $grossPay,
                    'net_pay' => $netPay,
                    'bank_name' => $staff->bank_name,
                    'account_number' => $staff->account_number,
                    'account_name' => $staff->account_name,
                ]);

                $totalGross += $grossPay;
                $totalDeductions += $effectiveDeductions;
                $totalNet += $netPay;
            }

            $run->update([
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
            ]);

            ActivityLogger::log(
                action: 'generated',
                module: 'Payroll',
                description: "Generated/recalculated payroll run: {$run->title}",
                subject: $run,
                properties: [
                    'month' => $month,
                    'year' => $year,
                    'total_net' => $run->total_net,
                ],
                user: $actor,
                institutionId: $institution->id
            );

            return $run->fresh(['items.staff', 'items.category', 'items.department']);
        });
    }

    /**
     * Recompute run-level totals after an item manual adjustment or post-approval edit.
     */
    public function syncRunTotals(PayrollRun $run): void
    {
        $items = $run->items()->get();

        $run->update([
            'total_gross' => round((float) $items->sum('gross_pay'), 2),
            'total_deductions' => round((float) $items->sum('total_deductions'), 2),
            'total_net' => round((float) $items->sum('net_pay'), 2),
        ]);
    }

    /**
     * Pull verified lecture attendances & course credit units for the given month.
     *
     * @return array{contacts_count: int, credit_units_total: float, hours_total: float, weighted_contact_units: float, weighted_hour_units: float}
     */
    public function computeContactMetrics(Staff $staff, int $institutionId, int $month, int $year): array
    {
        $userId = $staff->user?->id;
        if (! $userId) {
            return [
                'contacts_count' => 0,
                'credit_units_total' => 0.0,
                'hours_total' => 0.0,
                'weighted_contact_units' => 0.0,
                'weighted_hour_units' => 0.0,
            ];
        }

        $attendances = Attendance::query()
            ->with(['courseAllocation.course'])
            ->where('institution_id', $institutionId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('status', 'submitted')
            ->where('is_combined_child', false)
            ->whereHas('courseAllocation', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $contactsCount = 0;
        $creditUnitsTotal = 0.0;
        $hoursTotal = 0.0;
        $weightedContactUnits = 0.0;
        $weightedHourUnits = 0.0;

        foreach ($attendances as $attendance) {
            $contactsCount++;
            $creditUnit = (float) ($attendance->courseAllocation?->course?->credit_unit ?? 1);
            $durationHours = $this->calculateDurationHours($attendance->start_time, $attendance->end_time);

            $creditUnitsTotal += $creditUnit;
            $hoursTotal += $durationHours;
            $weightedContactUnits += $creditUnit; // 1 contact * credit_unit
            $weightedHourUnits += ($durationHours * $creditUnit);
        }

        return [
            'contacts_count' => $contactsCount,
            'credit_units_total' => $creditUnitsTotal,
            'hours_total' => $hoursTotal,
            'weighted_contact_units' => $weightedContactUnits,
            'weighted_hour_units' => $weightedHourUnits,
        ];
    }

    private function calculateDurationHours(?string $startTime, ?string $endTime): float
    {
        if (! $startTime || ! $endTime) {
            return 1.0;
        }

        try {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            $minutes = $start->diffInMinutes($end, false);

            return $minutes > 0 ? round($minutes / 60, 2) : 1.0;
        } catch (\Throwable) {
            return 1.0;
        }
    }

    /**
     * Filter polymorphic payroll components matching institution-wide, category, department, or staff.
     *
     * @param  Collection<int, PayrollComponent>  $components
     * @return Collection<int, PayrollComponent>
     */
    private function filterApplicableComponents(
        Collection $components,
        Staff $staff,
        ?int $categoryId,
        ?int $departmentId
    ): Collection {
        return $components->filter(function (PayrollComponent $comp) use ($staff, $categoryId, $departmentId): bool {
            if (! $comp->applicable_type || ! $comp->applicable_id) {
                return true;
            }

            if ($comp->applicable_type === Staff::class || $comp->applicable_type === (new Staff)->getMorphClass()) {
                return (int) $comp->applicable_id === $staff->id;
            }

            if ($categoryId && ($comp->applicable_type === PayrollCategory::class || $comp->applicable_type === (new PayrollCategory)->getMorphClass())) {
                return (int) $comp->applicable_id === $categoryId;
            }

            if ($departmentId && ($comp->applicable_type === Department::class || $comp->applicable_type === (new Department)->getMorphClass())) {
                return (int) $comp->applicable_id === $departmentId;
            }

            return false;
        });
    }
}
