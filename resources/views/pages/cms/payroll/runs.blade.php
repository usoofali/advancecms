<?php

use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\ActivityLogger;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Monthly Payroll Runs')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public ?int $run_id = null;

    #[Url]
    public string $search = '';

    // Approval & Disapproval Modals
    public bool $showApproveModal = false;

    public bool $showDisapproveModal = false;

    public bool $showDeleteModal = false;

    public string $disapprove_reason = '';

    // Manual Contact Override Modal
    public bool $showContactModal = false;

    public ?int $contactItemId = null;

    public string $contactStaffName = '';

    public string $contact_formula = 'contact_x_credit_unit';

    public float $contact_rate = 0.0;

    public int|string $contact_count_input = 0;

    public float|string $contact_units_input = 0;

    public float|string $contact_hours_input = 0;

    public bool $contact_is_overridden = false;

    public int $attendance_preview_contacts = 0;

    public float $attendance_preview_units = 0.0;

    public float $attendance_preview_hours = 0.0;

    // Generate Run Modal
    public bool $showGenerateModal = false;

    public int $gen_month = 1;

    public int $gen_year = 2026;

    public string $gen_title = '';

    // Manual Adjustment Modal
    public bool $showAdjustModal = false;

    public ?int $adjustItemId = null;

    public string $adjustStaffName = '';

    public string $adjust_amount = '0';

    public string $adjust_note = '';

    public function mount(): void
    {
        Gate::authorize('payroll.view');

        $this->gen_month = (int) date('n');
        $this->gen_year = (int) date('Y');

        $user = auth()->user();
        if (! $user->hasRole('Super Admin')) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id) {
            $this->institution_id = PayrollRun::latest('id')->value('institution_id')
                ?? Institution::where('status', 'active')->value('id')
                ?? Institution::value('id');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRunId(): void
    {
        $this->resetPage();
    }

    public function updatedInstitutionId(): void
    {
        $this->resetPage();
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: PayrollRun::latest('id')->value('institution_id') ?? Institution::where('status', 'active')->value('id') ?? Institution::value('id'))
            : $user->institution_id;
    }

    public function openGenerateModal(): void
    {
        Gate::authorize('payroll.create_run');
        $this->gen_title = Carbon::createFromDate($this->gen_year, $this->gen_month, 1)->format('F Y').' Payroll';
        $this->showGenerateModal = true;
    }

    public function generateRun(PayrollCalculator $calculator): void
    {
        Gate::authorize('payroll.create_run');

        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $this->validate([
            'gen_month' => 'required|integer|min:1|max:12',
            'gen_year' => 'required|integer|min:2020|max:2100',
            'gen_title' => 'nullable|string|max:120',
        ]);

        $institution = Institution::findOrFail($instId);

        $existing = PayrollRun::where('institution_id', $instId)
            ->where('month', $this->gen_month)
            ->where('year', $this->gen_year)
            ->first();

        if ($existing && in_array($existing->status, ['under_review', 'approved', 'paid'])) {
            $this->addError('gen_month', __('This period is already under review, approved, or paid and cannot be overwritten.'));

            return;
        }

        try {
            $run = $calculator->generateOrRecalculateRun(
                $institution,
                $this->gen_month,
                $this->gen_year,
                auth()->user(),
                $this->gen_title ?: null
            );

            $this->run_id = $run->id;
            $this->showGenerateModal = false;
            $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll run calculated successfully.')]);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('gen_month', __('Unable to calculate payroll run: :message', ['message' => $e->getMessage()]));
        }
    }

    public function recalculateSelectedRun(PayrollCalculator $calculator): void
    {
        Gate::authorize('payroll.create_run');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if ($run->status !== 'draft') {
            return;
        }

        try {
            $calculator->generateOrRecalculateRun(
                Institution::findOrFail($instId),
                $run->month,
                $run->year,
                auth()->user(),
                $run->title
            );

            $this->dispatch('notify', ['type' => 'success', 'message' => __('Draft payroll recalculated.')]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Recalculation failed: :message', ['message' => $e->getMessage()])]);
        }
    }

    public function submitForReview(): void
    {
        Gate::authorize('payroll.create_run');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if ($run->status !== 'draft') {
            return;
        }

        $run->update(['status' => 'under_review']);

        ActivityLogger::log(
            action: 'submitted',
            module: 'Payroll',
            description: "Submitted payroll run for approval: {$run->title}",
            subject: $run,
            institutionId: $instId
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Submitted for administrative approval.')]);
    }

    public function approveRun(): void
    {
        Gate::authorize('payroll.approve');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if (! in_array($run->status, ['draft', 'under_review'])) {
            return;
        }

        $run->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ActivityLogger::log(
            action: 'approved',
            module: 'Payroll',
            description: "Approved payroll run: {$run->title} (Net: ₦".number_format((float) $run->total_net, 2).')',
            subject: $run,
            institutionId: $instId
        );

        $this->showApproveModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll run approved and finalized.')]);
    }

    public function openDisapproveModal(): void
    {
        Gate::authorize('payroll.approve');
        $this->disapprove_reason = '';
        $this->resetValidation('disapprove_reason');
        $this->showDisapproveModal = true;
    }

    public function disapproveRun(): void
    {
        Gate::authorize('payroll.approve');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if (! in_array($run->status, ['under_review', 'approved'])) {
            return;
        }

        $this->validate([
            'disapprove_reason' => 'required|string|max:500',
        ], [
            'disapprove_reason.required' => __('Please provide a reason for disapproving this payroll run.'),
        ]);

        $run->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'notes' => $this->disapprove_reason,
        ]);

        ActivityLogger::log(
            action: 'disapproved',
            module: 'Payroll',
            description: "Disapproved payroll run ({$run->title}) and returned to draft: {$this->disapprove_reason}",
            subject: $run,
            institutionId: $instId
        );

        $this->showDisapproveModal = false;
        $this->disapprove_reason = '';
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll run disapproved and returned to draft.')]);
    }

    public function deleteRun(): void
    {
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if ($run->status === 'paid') {
            $this->showDeleteModal = false;

            return;
        }

        if ($run->status === 'approved') {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $title = $run->title;
        ActivityLogger::log(
            action: 'deleted',
            module: 'Payroll',
            description: "Deleted payroll run: {$title}",
            subject: $run,
            institutionId: $instId
        );

        $run->delete();
        $this->run_id = null;
        $this->showDeleteModal = false;
        $this->resetPage();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll run deleted.')]);
    }

    public function openContactModal(int $itemId, PayrollCalculator $calculator): void
    {
        $instId = $this->resolveInstitutionId();
        $item = PayrollItem::with(['staff.payrollProfile.category', 'staff.user', 'run', 'category'])
            ->where('institution_id', $instId)
            ->findOrFail($itemId);

        if ($item->pay_type === 'fixed') {
            return;
        }

        if (in_array($item->run->status, ['approved', 'paid'])) {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $defaultCategory = PayrollCategory::query()
            ->where('institution_id', $instId)
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();

        $profile = $item->staff?->payrollProfile;
        $formula = $profile
            ? $profile->effectiveContactFormula($defaultCategory)
            : ($item->category?->contact_formula ?? $defaultCategory?->contact_formula ?? 'contact_x_credit_unit');

        $attendanceStats = $item->staff
            ? $calculator->computeContactMetrics($item->staff, $instId, (int) $item->run->month, (int) $item->run->year)
            : ['contacts_count' => 0, 'credit_units_total' => 0.0, 'hours_total' => 0.0];

        $this->contactItemId = $item->id;
        $this->contactStaffName = trim("{$item->staff?->first_name} {$item->staff?->last_name}");
        $this->contact_formula = $formula;
        $this->contact_rate = (float) $item->rate_used;
        $this->contact_count_input = (int) $item->contacts_count;
        $this->contact_units_input = (float) $item->credit_units_total;
        $this->contact_hours_input = (float) $item->hours_total;
        $this->contact_is_overridden = (bool) $item->is_contact_overridden;
        $this->attendance_preview_contacts = (int) $attendanceStats['contacts_count'];
        $this->attendance_preview_units = (float) $attendanceStats['credit_units_total'];
        $this->attendance_preview_hours = (float) $attendanceStats['hours_total'];
        $this->resetValidation(['contact_count_input', 'contact_units_input', 'contact_hours_input']);
        $this->showContactModal = true;
    }

    public function saveContactOverride(PayrollCalculator $calculator): void
    {
        $instId = $this->resolveInstitutionId();
        $item = PayrollItem::with(['run', 'staff'])->where('institution_id', $instId)->findOrFail($this->contactItemId);

        if (in_array($item->run->status, ['approved', 'paid'])) {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $this->validate([
            'contact_count_input' => 'required|integer|min:0',
            'contact_units_input' => 'required|numeric|min:0',
            'contact_hours_input' => 'required|numeric|min:0',
        ]);

        $calculator->applyContactOverride(
            $item,
            (int) $this->contact_count_input,
            (float) $this->contact_units_input,
            (float) $this->contact_hours_input,
            true
        );

        ActivityLogger::log(
            action: 'contact_override',
            module: 'Payroll',
            description: "Updated contacts for {$item->staff?->first_name} {$item->staff?->last_name} in {$item->run->title}: {$this->contact_count_input} contacts, {$this->contact_units_input} units, {$this->contact_hours_input} hrs",
            subject: $item->run,
            institutionId: $instId
        );

        $this->showContactModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Contacts updated and pay recalculated.')]);
    }

    public function resetContactToAttendance(PayrollCalculator $calculator): void
    {
        $instId = $this->resolveInstitutionId();
        $item = PayrollItem::with(['run', 'staff.user'])->where('institution_id', $instId)->findOrFail($this->contactItemId);

        if (in_array($item->run->status, ['approved', 'paid'])) {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $stats = $item->staff
            ? $calculator->computeContactMetrics($item->staff, $instId, (int) $item->run->month, (int) $item->run->year)
            : ['contacts_count' => 0, 'credit_units_total' => 0.0, 'hours_total' => 0.0, 'weighted_contact_units' => 0.0, 'weighted_hour_units' => 0.0];

        $calculator->applyContactOverride(
            $item,
            (int) $stats['contacts_count'],
            (float) $stats['credit_units_total'],
            (float) $stats['hours_total'],
            false,
            (float) $stats['weighted_contact_units'],
            (float) $stats['weighted_hour_units']
        );

        $this->showContactModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Contacts reset to Attendance records.')]);
    }

    public function markPaid(): void
    {
        Gate::authorize('payroll.mark_paid');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if ($run->status !== 'approved') {
            return;
        }

        $run->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        ActivityLogger::log(
            action: 'paid',
            module: 'Payroll',
            description: "Marked payroll run as paid: {$run->title}",
            subject: $run,
            institutionId: $instId
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Payroll marked as paid.')]);
    }

    public function openAdjustModal(int $itemId): void
    {
        $instId = $this->resolveInstitutionId();
        $item = PayrollItem::with(['staff', 'run'])->where('institution_id', $instId)->findOrFail($itemId);

        if (in_array($item->run->status, ['approved', 'paid'])) {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $this->adjustItemId = $item->id;
        $this->adjustStaffName = trim("{$item->staff->first_name} {$item->staff->last_name}");
        $this->adjust_amount = (string) $item->manual_adjustment;
        $this->adjust_note = (string) ($item->adjustment_note ?? '');
        $this->showAdjustModal = true;
    }

    public function saveAdjustment(PayrollCalculator $calculator): void
    {
        $instId = $this->resolveInstitutionId();
        $item = PayrollItem::with(['run', 'staff'])->where('institution_id', $instId)->findOrFail($this->adjustItemId);

        $isPostApproval = in_array($item->run->status, ['approved', 'paid']);
        if ($isPostApproval) {
            Gate::authorize('payroll.approve');
        } else {
            Gate::authorize('payroll.create_run');
        }

        $this->validate([
            'adjust_amount' => 'required|numeric',
            'adjust_note' => $isPostApproval ? 'required|string|max:255' : 'nullable|string|max:255',
        ]);

        $newAdj = (float) $this->adjust_amount;
        $baseAndAllowances = (float) $item->base_pay + (float) $item->total_allowances;
        $baseDeductions = collect($item->deductions_breakdown ?? [])->sum('amount');

        $grossPay = round($baseAndAllowances + max(0, $newAdj), 2);
        $totalDeductions = round($baseDeductions + ($newAdj < 0 ? abs($newAdj) : 0), 2);
        $netPay = round(max(0, $grossPay - $totalDeductions), 2);

        $item->update([
            'manual_adjustment' => $newAdj,
            'adjustment_note' => $this->adjust_note ?: null,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);

        $calculator->syncRunTotals($item->run);

        if ($isPostApproval) {
            ActivityLogger::log(
                action: 'post_approval_edit',
                module: 'Payroll',
                description: "Post-approval adjustment for {$item->staff->first_name} {$item->staff->last_name} in {$item->run->title}: {$newAdj} ({$this->adjust_note})",
                subject: $item->run,
                institutionId: $instId
            );
        }

        $this->showAdjustModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Adjustment saved.')]);
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $runs = $instId
            ? PayrollRun::with(['preparer', 'approver'])
                ->where('institution_id', $instId)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get()
            : collect();

        $selectedRun = $this->run_id
            ? $runs->firstWhere('id', $this->run_id)
            : $runs->first();

        if ($selectedRun && ! $this->run_id) {
            $this->run_id = $selectedRun->id;
        }

        $items = $selectedRun
            ? PayrollItem::with(['staff', 'category', 'department'])
                ->where('payroll_run_id', $selectedRun->id)
                ->when($this->search, function ($q) {
                    $q->whereHas('staff', function ($sq) {
                        $sq->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('staff_number', 'like', "%{$this->search}%");
                    });
                })
                ->orderBy('id')
                ->paginate(15)
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);

        $missingBankCount = 0;
        $zeroPayCount = 0;

        if ($selectedRun) {
            $missingBankCount = PayrollItem::where('payroll_run_id', $selectedRun->id)
                ->where(function ($q) {
                    $q->whereNull('bank_name')
                        ->orWhere('bank_name', '')
                        ->orWhereNull('account_number')
                        ->orWhere('account_number', '');
                })
                ->count();

            $zeroPayCount = PayrollItem::where('payroll_run_id', $selectedRun->id)
                ->where('net_pay', '<=', 0)
                ->count();
        }

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'items' => $items,
            'missingBankCount' => $missingBankCount,
            'zeroPayCount' => $zeroPayCount,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Monthly Payroll Runs') }}</flux:heading>
            <flux:subheading>{{ __('Generate monthly pay calculations, review deductions, approve runs, and export bank schedules') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-56">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @can('payroll.create_run')
                <flux:button variant="primary" icon="plus" wire:click="openGenerateModal" class="w-full sm:w-auto">
                    {{ __('New Monthly Run') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Run Selector & Workflow Bar --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 shadow-xs space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <flux:select wire:model.live="run_id" :label="__('Select Payroll Period')">
                @forelse($runs as $r)
                    <flux:select.option :value="$r->id">{{ $r->title }} ({{ __($r->statusLabel()) }})</flux:select.option>
                @empty
                    <flux:select.option value="">{{ __('No payroll runs yet') }}</flux:select.option>
                @endforelse
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search Staff')" icon="magnifying-glass" :placeholder="__('Name or staff ID...')" clearable />

            @if($selectedRun)
                <div class="flex flex-wrap items-center justify-start md:justify-end gap-2">
                    @if($selectedRun->status === 'draft')
                        @can('payroll.create_run')
                            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="recalculateSelectedRun">
                                {{ __('Recalculate') }}
                            </flux:button>
                            <flux:button size="sm" variant="filled" icon="paper-airplane" wire:click="submitForReview">
                                {{ __('Submit for Review') }}
                            </flux:button>
                        @endcan
                    @endif

                    @if(in_array($selectedRun->status, ['draft', 'under_review']))
                        @can('payroll.approve')
                            <flux:button size="sm" variant="primary" icon="check-badge" wire:click="$set('showApproveModal', true)">
                                {{ __('Approve Payroll') }}
                            </flux:button>
                        @endcan
                    @endif

                    @if(in_array($selectedRun->status, ['under_review', 'approved']))
                        @can('payroll.approve')
                            <flux:button size="sm" variant="danger" icon="arrow-uturn-left" wire:click="openDisapproveModal">
                                {{ __('Disapprove') }}
                            </flux:button>
                        @endcan
                    @endif

                    @if($selectedRun->status === 'approved')
                        @can('payroll.mark_paid')
                            <flux:button size="sm" variant="primary" icon="banknotes" wire:click="markPaid" wire:confirm="{{ __('Mark this payroll run as paid/disbursed?') }}">
                                {{ __('Mark as Paid') }}
                            </flux:button>
                        @endcan
                    @endif

                    @if($selectedRun->status !== 'paid' && (($selectedRun->status === 'approved' && auth()->user()->can('payroll.approve')) || ($selectedRun->status !== 'approved' && auth()->user()->can('payroll.create_run'))))
                        <flux:button size="sm" variant="ghost" icon="trash" class="text-rose-600 hover:text-rose-700" wire:click="$set('showDeleteModal', true)">
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>

        @if($selectedRun)
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge size="sm" :color="$selectedRun->statusColor()">{{ __($selectedRun->statusLabel()) }}</flux:badge>
                    <span>{{ __('Gross:') }} <strong>₦{{ number_format((float) $selectedRun->total_gross, 2) }}</strong></span>
                    <span>{{ __('Deductions:') }} <strong class="text-red-600 dark:text-red-400">₦{{ number_format((float) $selectedRun->total_deductions, 2) }}</strong></span>
                    <span>{{ __('Net Payout:') }} <strong class="text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $selectedRun->total_net, 2) }}</strong></span>
                </div>

                @can('payroll.reports')
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button size="xs" variant="ghost" icon="building-office" :href="route('cms.payroll.department-sheet', ['run' => $selectedRun->id])" wire:navigate>
                            {{ __('Department Sheet') }}
                        </flux:button>
                        <flux:button size="xs" variant="ghost" icon="building-library" :href="route('cms.payroll.bank-schedule', ['run' => $selectedRun->id])" wire:navigate>
                            {{ __('Bank Schedule') }}
                        </flux:button>
                    </div>
                @endcan
            </div>
        @endif
    </div>

    @if($selectedRun)
        @if($missingBankCount > 0 || $zeroPayCount > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 font-semibold">
                        <flux:icon.exclamation-triangle class="size-4 text-amber-600 dark:text-amber-400" />
                        {{ __('Payroll Attention Needed:') }}
                    </span>
                    @if($missingBankCount > 0)
                        <span>{{ __(':count staff missing bank details', ['count' => $missingBankCount]) }}</span>
                    @endif
                    @if($zeroPayCount > 0)
                        <span>{{ __(':count staff with ₦0.00 net pay', ['count' => $zeroPayCount]) }}</span>
                    @endif
                </div>
            </div>
        @endif

        <flux:table :paginate="$items">
            <flux:table.columns>
                <flux:table.column>{{ __('Staff') }}</flux:table.column>
                <flux:table.column>{{ __('Pay Basis') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Base Pay') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Allowances') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Deductions') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Net Pay') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($items as $item)
                    <flux:table.row wire:key="run-item-{{ $item->id }}">
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $item->staff?->first_name }} {{ $item->staff?->last_name }}
                                </span>
                                @if(empty($item->bank_name) || empty($item->account_number))
                                    <flux:badge size="xs" color="amber">{{ __('Missing Bank') }}</flux:badge>
                                @endif
                                @if((float) $item->net_pay <= 0)
                                    <flux:badge size="xs" color="rose">{{ __('₦0 Pay') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ $item->category?->name ?? __('Default') }} {{ $item->department ? '• '.$item->department->name : '' }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs">
                            @if($item->pay_type === 'fixed')
                                <flux:badge size="sm" color="emerald">{{ __('Fixed') }}</flux:badge>
                            @else
                                <div class="flex flex-wrap items-center gap-1.5 font-medium text-amber-700 dark:text-amber-300">
                                    <span>{{ $item->contacts_count }} {{ __('contacts') }} &bull; {{ (float) $item->credit_units_total }} {{ __('units') }}</span>
                                    @if($item->is_contact_overridden)
                                        <flux:badge size="xs" color="indigo">{{ __('Manual') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="text-zinc-500">@ ₦{{ number_format((float) $item->rate_used, 2) }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-right font-mono text-xs">
                            ₦{{ number_format((float) $item->base_pay, 2) }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">
                            +₦{{ number_format((float) $item->total_allowances + max(0, (float) $item->manual_adjustment), 2) }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right font-mono text-xs text-red-600 dark:text-red-400">
                            -₦{{ number_format((float) $item->total_deductions, 2) }}
                        </flux:table.cell>

                        <flux:table.cell class="text-right font-bold text-zinc-900 dark:text-white">
                            ₦{{ number_format((float) $item->net_pay, 2) }}
                            @if($item->adjustment_note)
                                <div class="text-[10px] font-normal text-amber-600">{{ $item->adjustment_note }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" aria-label="{{ __('Actions') }}" />

                                <flux:menu>
                                    @if((!in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.create_run')) || (in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.approve')))
                                        @if($item->pay_type !== 'fixed')
                                            <flux:menu.item icon="clock" wire:click="openContactModal({{ $item->id }})">
                                                {{ __('Update Contacts') }}
                                            </flux:menu.item>
                                        @endif
                                        <flux:menu.item icon="pencil" wire:click="openAdjustModal({{ $item->id }})">
                                            {{ __('Adjust Payline') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.item icon="document-text" :href="route('cms.payroll.payslip', ['item' => $item->id])" target="_blank">
                                        {{ __('View Payslip') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">
                            {{ __('No staff paylines in this run.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Approval Confirmation Modal --}}
        <flux:modal wire:model.self="showApproveModal" class="max-w-md w-full">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Approve Payroll Run?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Approving ":title" will finalize the payroll figures and make payslips available to staff.', ['title' => $selectedRun->title]) }}
                    </flux:subheading>
                </div>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50 p-4 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Total Gross Pay') }}</span>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-white">₦{{ number_format((float) $selectedRun->total_gross, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Total Deductions') }}</span>
                        <span class="font-mono font-semibold text-red-600 dark:text-red-400">-₦{{ number_format((float) $selectedRun->total_deductions, 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 dark:border-zinc-700 pt-2 text-sm font-bold">
                        <span>{{ __('Total Net Payout') }}</span>
                        <span class="font-mono text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $selectedRun->total_net, 2) }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check-badge" wire:click="approveRun" wire:loading.attr="disabled">
                        {{ __('Confirm & Approve') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
        {{-- Delete Run Confirmation Modal --}}
        <flux:modal wire:model.self="showDeleteModal" class="max-w-md w-full">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Delete Payroll Run?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Are you sure you want to delete ":title"? All calculated paylines for this period will be permanently removed.', ['title' => $selectedRun->title]) }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" icon="trash" wire:click="deleteRun" wire:loading.attr="disabled">
                        {{ __('Delete Payroll') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Disapprove / Return to Draft Modal --}}
        <flux:modal wire:model.self="showDisapproveModal" class="max-w-md w-full">
            <form wire:submit="disapproveRun" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Disapprove Payroll Run?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Returning ":title" to draft will allow recalculation, contact updates, and payline edits before re-approval.', ['title' => $selectedRun->title]) }}
                    </flux:subheading>
                </div>

                <flux:textarea wire:model="disapprove_reason" :label="__('Reason for Disapproval')" :placeholder="__('e.g. Missing casual lecturer contacts or incorrect deductions')" rows="3" required />
                <flux:error name="disapprove_reason" />

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" type="submit" icon="arrow-uturn-left" wire:loading.attr="disabled">
                        {{ __('Disapprove & Return to Draft') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Update Contacts Modal --}}
        <flux:modal wire:model.self="showContactModal" class="max-w-md w-full">
            <form wire:submit="saveContactOverride" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Update Monthly Contacts') }}</flux:heading>
                    <flux:subheading>{{ $contactStaffName }}</flux:subheading>
                </div>

                <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3 text-xs text-sky-900 dark:border-sky-800/60 dark:bg-sky-950/30 dark:text-sky-200">
                    <div class="font-semibold">{{ __('Attendance Captured:') }}</div>
                    <div class="mt-0.5">
                        {{ $attendance_preview_contacts }} {{ __('contacts') }} &bull; {{ $attendance_preview_units }} {{ __('credit units') }} &bull; {{ $attendance_preview_hours }} {{ __('hours') }}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <flux:input type="number" min="0" wire:model.live="contact_count_input" :label="__('Contacts')" required />
                    <flux:input type="number" step="0.5" min="0" wire:model.live="contact_units_input" :label="__('Credit Units')" required />
                    <flux:input type="number" step="0.5" min="0" wire:model.live="contact_hours_input" :label="__('Hours')" required />
                </div>

                @php
                    $previewBasePay = app(\App\Services\PayrollCalculator::class)->computeContactBasePay(
                        $contact_formula,
                        $contact_rate,
                        (int) ($contact_count_input ?: 0),
                        (float) ($contact_units_input ?: 0),
                        (float) ($contact_hours_input ?: 0)
                    );
                @endphp
                <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 px-3.5 py-2.5 text-xs">
                    <span class="text-zinc-500">{{ __('Preview Base Pay (@ ₦:rate)', ['rate' => number_format($contact_rate, 2)]) }}</span>
                    <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">₦{{ number_format($previewBasePay, 2) }}</span>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    @if($contact_is_overridden)
                        <flux:button variant="ghost" size="sm" type="button" wire:click="resetContactToAttendance">
                            {{ __('Reset to Attendance') }}
                        </flux:button>
                    @else
                        <div></div>
                    @endif

                    <div class="flex items-center gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                            {{ __('Save Contacts') }}
                        </flux:button>
                    </div>
                </div>
            </form>
        </flux:modal>
    @endif

    {{-- Generate Run Modal --}}
    <flux:modal wire:model.self="showGenerateModal" class="max-w-md w-full">
        <form wire:submit="generateRun" class="space-y-4">
            <flux:heading size="lg">{{ __('Generate Monthly Payroll') }}</flux:heading>

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model.live="gen_month" :label="__('Month')">
                    @for($m = 1; $m <= 12; $m++)
                        <flux:select.option :value="$m">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</flux:select.option>
                    @endfor
                </flux:select>

                <flux:input type="number" wire:model.live="gen_year" :label="__('Year')" min="2020" max="2100" required />
            </div>

            <flux:input wire:model="gen_title" :label="__('Title')" required />

            @error('gen_month')
                <div class="text-xs text-red-600">{{ $message }}</div>
            @enderror

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showGenerateModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Calculate & Create Draft') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Manual Adjustment Modal --}}
    <flux:modal wire:model.self="showAdjustModal" class="max-w-md w-full">
        <form wire:submit="saveAdjustment" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Adjust Payline') }}</flux:heading>
                <flux:subheading>{{ $adjustStaffName }}</flux:subheading>
            </div>

            <flux:input type="number" step="0.01" wire:model="adjust_amount" :label="__('Adjustment Amount (₦)')" :description="__('Use positive value for bonus/addition, negative (e.g. -5000) for deduction')" required />

            <flux:input wire:model="adjust_note" :label="__('Reason / Note')" :placeholder="__('e.g. Weekend clinical bonus / Salary advance')" />

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showAdjustModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Adjustment') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
