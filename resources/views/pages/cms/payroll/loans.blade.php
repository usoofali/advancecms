<?php

use App\Models\Institution;
use App\Models\Staff;
use App\Models\StaffLoan;
use App\Models\StaffLoanRepayment;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Staff Loans')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    // Create / Edit Loan Modal
    public bool $showLoanModal = false;

    public ?int $editingLoanId = null;

    public ?int $staff_id = null;

    public string $title = '';

    public string $reference_no = '';

    public string $principal_amount = '';

    public string $monthly_deduction = '';

    public int $start_month = 1;

    public int $start_year = 2026;

    public string $notes = '';

    // Manual Repayment Modal
    public bool $showRepaymentModal = false;

    public ?int $repayingLoanId = null;

    public string $repayment_amount = '';

    public string $repayment_notes = '';

    // History Modal
    public bool $showHistoryModal = false;

    public ?int $historyLoanId = null;

    public function mount(): void
    {
        abort_unless(Gate::any(['payroll.view', 'payroll.manage_loans']), 403);

        $user = auth()->user();
        if (! $user->hasRole('Super Admin')) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }

        $this->start_month = (int) now()->month;
        $this->start_year = (int) now()->year;
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: Institution::where('status', 'active')->value('id'))
            : $user->institution_id;
    }

    public function updatedInstitutionId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('payroll.manage_loans');

        $this->resetValidation();
        $this->editingLoanId = null;
        $this->staff_id = null;
        $this->title = '';
        $this->reference_no = '';
        $this->principal_amount = '';
        $this->monthly_deduction = '';
        $this->start_month = (int) now()->month;
        $this->start_year = (int) now()->year;
        $this->notes = '';
        $this->showLoanModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();
        $loan = StaffLoan::where('institution_id', $instId)->findOrFail($id);

        if (! in_array($loan->status, ['active', 'paused'])) {
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Only active or paused loans can be edited.')]);

            return;
        }

        $this->resetValidation();
        $this->editingLoanId = $loan->id;
        $this->staff_id = $loan->staff_id;
        $this->title = $loan->title;
        $this->reference_no = (string) ($loan->reference_no ?? '');
        $this->principal_amount = (string) $loan->principal_amount;
        $this->monthly_deduction = (string) $loan->monthly_deduction;
        $this->start_month = (int) $loan->start_month;
        $this->start_year = (int) $loan->start_year;
        $this->notes = (string) ($loan->notes ?? '');
        $this->showLoanModal = true;
    }

    public function saveLoan(): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'monthly_deduction' => ['required', 'numeric', 'min:1'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'start_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $this->editingLoanId) {
            $rules['staff_id'] = ['required', 'exists:staff,id'];
            $rules['principal_amount'] = ['required', 'numeric', 'min:1'];
        }

        $this->validate($rules);

        if ($this->editingLoanId) {
            $loan = StaffLoan::where('institution_id', $instId)->findOrFail($this->editingLoanId);
            if ((float) $this->monthly_deduction > (float) $loan->remaining_balance) {
                $this->addError('monthly_deduction', __('Monthly deduction cannot exceed remaining balance.'));

                return;
            }

            $loan->update([
                'title' => $this->title,
                'reference_no' => $this->reference_no ?: null,
                'monthly_deduction' => round((float) $this->monthly_deduction, 2),
                'start_month' => $this->start_month,
                'start_year' => $this->start_year,
                'notes' => $this->notes ?: null,
            ]);

            ActivityLogger::log(
                action: 'updated',
                module: 'Payroll',
                description: "Updated staff loan ({$loan->title}) for staff #{$loan->staff_id}",
                subject: $loan,
                institutionId: $instId
            );

            $this->showLoanModal = false;
            $this->dispatch('notify', ['type' => 'success', 'message' => __('Staff loan updated.')]);

            return;
        }

        $staff = Staff::where('institution_id', $instId)->findOrFail($this->staff_id);
        $principal = round((float) $this->principal_amount, 2);
        $monthly = round((float) $this->monthly_deduction, 2);

        if ($monthly > $principal) {
            $this->addError('monthly_deduction', __('Monthly deduction cannot exceed the principal amount.'));

            return;
        }

        $maxLoans = (int) (Institution::find($instId)?->max_concurrent_staff_loans ?? 1);
        $openCount = StaffLoan::query()->openForStaff($staff->id)->count();

        if ($openCount >= $maxLoans) {
            $this->addError(
                'staff_id',
                __('This staff member already has :count open loan(s). Institution limit is :max concurrent open loan(s).', [
                    'count' => $openCount,
                    'max' => $maxLoans,
                ])
            );

            return;
        }

        $loan = StaffLoan::create([
            'institution_id' => $instId,
            'staff_id' => $staff->id,
            'title' => $this->title,
            'reference_no' => $this->reference_no ?: null,
            'principal_amount' => $principal,
            'monthly_deduction' => $monthly,
            'total_repaid' => 0,
            'remaining_balance' => $principal,
            'start_month' => $this->start_month,
            'start_year' => $this->start_year,
            'status' => 'active',
            'notes' => $this->notes ?: null,
            'created_by' => auth()->id(),
        ]);

        ActivityLogger::log(
            action: 'created',
            module: 'Payroll',
            description: "Created staff loan ({$loan->title}) of ₦".number_format($principal, 2)." for {$staff->first_name} {$staff->last_name}",
            subject: $loan,
            institutionId: $instId
        );

        $this->showLoanModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Staff loan created successfully.')]);
    }

    public function togglePause(int $id): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();
        $loan = StaffLoan::where('institution_id', $instId)->findOrFail($id);

        if (! in_array($loan->status, ['active', 'paused'])) {
            return;
        }

        $newStatus = $loan->status === 'active' ? 'paused' : 'active';
        $loan->update(['status' => $newStatus]);

        ActivityLogger::log(
            action: $newStatus === 'paused' ? 'paused' : 'resumed',
            module: 'Payroll',
            description: ucfirst($newStatus)." loan ({$loan->title}) for staff #{$loan->staff_id}",
            subject: $loan,
            institutionId: $instId
        );

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $newStatus === 'paused' ? __('Loan deductions paused.') : __('Loan deductions resumed.'),
        ]);
    }

    public function cancelLoan(int $id): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();
        $loan = StaffLoan::where('institution_id', $instId)->findOrFail($id);

        if (! in_array($loan->status, ['active', 'paused'])) {
            return;
        }

        $loan->update(['status' => 'cancelled']);

        ActivityLogger::log(
            action: 'cancelled',
            module: 'Payroll',
            description: "Cancelled staff loan ({$loan->title}) for staff #{$loan->staff_id}",
            subject: $loan,
            institutionId: $instId
        );

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Loan cancelled.')]);
    }

    public function openRepaymentModal(int $id): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();
        $loan = StaffLoan::where('institution_id', $instId)->findOrFail($id);

        if (! in_array($loan->status, ['active', 'paused']) || (float) $loan->remaining_balance <= 0) {
            return;
        }

        $this->resetValidation();
        $this->repayingLoanId = $loan->id;
        $this->repayment_amount = (string) $loan->dueInstallmentAmount();
        $this->repayment_notes = '';
        $this->showRepaymentModal = true;
    }

    public function recordManualRepayment(): void
    {
        Gate::authorize('payroll.manage_loans');
        $instId = $this->resolveInstitutionId();

        $this->validate([
            'repayment_amount' => ['required', 'numeric', 'min:0.01'],
            'repayment_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = round((float) $this->repayment_amount, 2);

        DB::transaction(function () use ($instId, $amount): void {
            $loan = StaffLoan::where('institution_id', $instId)
                ->lockForUpdate()
                ->findOrFail($this->repayingLoanId);

            $balanceBefore = (float) $loan->remaining_balance;
            if ($amount > $balanceBefore) {
                $this->addError('repayment_amount', __('Repayment cannot exceed remaining balance (₦:bal).', [
                    'bal' => number_format($balanceBefore, 2),
                ]));

                return;
            }

            $balanceAfter = round(max(0, $balanceBefore - $amount), 2);

            StaffLoanRepayment::create([
                'staff_loan_id' => $loan->id,
                'institution_id' => $loan->institution_id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_source' => 'manual',
                'paid_at' => now(),
                'recorded_by' => auth()->id(),
                'notes' => $this->repayment_notes ?: __('Manual repayment'),
            ]);

            $loan->update([
                'total_repaid' => round((float) $loan->total_repaid + $amount, 2),
                'remaining_balance' => $balanceAfter,
                'status' => $balanceAfter <= 0 ? 'completed' : $loan->status,
            ]);

            ActivityLogger::log(
                action: 'repayment_recorded',
                module: 'Payroll',
                description: 'Recorded manual loan repayment of ₦'.number_format($amount, 2)." for {$loan->title}",
                subject: $loan,
                institutionId: $instId
            );
        });

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->showRepaymentModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Repayment recorded successfully.')]);
    }

    public function openHistoryModal(int $id): void
    {
        $this->historyLoanId = $id;
        $this->showHistoryModal = true;
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $baseQuery = StaffLoan::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId));

        $openLoans = (clone $baseQuery)->whereIn('status', ['active', 'paused'])->get();

        $loans = (clone $baseQuery)
            ->with('staff')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q): void {
                $term = "%{$this->search}%";
                $q->where(function ($sub) use ($term): void {
                    $sub->where('title', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('staff', fn ($sq) => $sq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('staff_number', 'like', $term));
                });
            })
            ->latest()
            ->paginate(15);

        $historyLoan = $this->historyLoanId
            ? StaffLoan::with(['staff', 'repayments.recorder', 'repayments.payrollRun'])
                ->where('institution_id', $instId)
                ->find($this->historyLoanId)
            : null;

        $repayingLoan = $this->repayingLoanId
            ? StaffLoan::with('staff')->where('institution_id', $instId)->find($this->repayingLoanId)
            : null;

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'staffList' => $instId
                ? Staff::where('institution_id', $instId)->where('status', 'active')->orderBy('first_name')->get()
                : collect(),
            'loans' => $loans,
            'maxConcurrentLoans' => (int) (Institution::find($instId)?->max_concurrent_staff_loans ?? 1),
            'activeCount' => $openLoans->count(),
            'totalPrincipal' => (float) $openLoans->sum('principal_amount'),
            'totalOutstanding' => (float) $openLoans->sum('remaining_balance'),
            'monthlyRecovery' => (float) $openLoans->where('status', 'active')->sum(fn ($l) => $l->dueInstallmentAmount()),
            'historyLoan' => $historyLoan,
            'repayingLoan' => $repayingLoan,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Staff Loans') }}</flux:heading>
            <flux:subheading>
                {{ __('Manage staff loans, monthly installment deductions, and repayment ledgers.') }}
                <span class="font-semibold text-zinc-700 dark:text-zinc-300">
                    ({{ __('Max concurrent open loans per staff: :max', ['max' => $maxConcurrentLoans]) }})
                </span>
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin') && $institutions->isNotEmpty())
                <div class="w-full sm:w-56">
                    <flux:select wire:model.live="institution_id">
                        @foreach($institutions as $inst)
                            <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            @can('payroll.manage_loans')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal" class="w-full sm:w-auto">
                    {{ __('New Staff Loan') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-1">
            <flux:text size="xs" class="uppercase tracking-wider text-zinc-500">{{ __('Open Loans') }}</flux:text>
            <flux:heading size="xl">{{ $activeCount }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="xs" class="uppercase tracking-wider text-zinc-500">{{ __('Open Principal') }}</flux:text>
            <flux:heading size="xl">₦{{ number_format($totalPrincipal, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="xs" class="uppercase tracking-wider text-zinc-500">{{ __('Outstanding Balance') }}</flux:text>
            <flux:heading size="xl" class="text-amber-600 dark:text-amber-400">₦{{ number_format($totalOutstanding, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="xs" class="uppercase tracking-wider text-zinc-500">{{ __('Scheduled Monthly Recovery') }}</flux:text>
            <flux:heading size="xl" class="text-emerald-600 dark:text-emerald-400">₦{{ number_format($monthlyRecovery, 2) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search staff, loan title, ref...')" />
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="all">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="paused">{{ __('Paused') }}</flux:select.option>
                <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Loans Table --}}
    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$loans">
                <flux:table.columns>
                    <flux:table.column>{{ __('Staff') }}</flux:table.column>
                    <flux:table.column>{{ __('Loan / Ref') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Principal') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Monthly') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Remaining') }}</flux:table.column>
                    <flux:table.column>{{ __('Starts') }}</flux:table.column>
                    <flux:table.column class="text-center">{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($loans as $loan)
                        <flux:table.row :key="$loan->id">
                            <flux:table.cell>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $loan->staff?->first_name }} {{ $loan->staff?->last_name }}
                                </div>
                                <div class="text-xs font-mono text-zinc-500">{{ $loan->staff?->staff_number }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $loan->title }}</div>
                                @if($loan->reference_no)
                                    <div class="text-xs font-mono text-zinc-500">{{ $loan->reference_no }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-mono text-xs">
                                ₦{{ number_format((float) $loan->principal_amount, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-mono text-xs">
                                ₦{{ number_format((float) $loan->monthly_deduction, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                ₦{{ number_format((float) $loan->remaining_balance, 2) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                {{ Carbon::createFromDate($loan->start_year, $loan->start_month, 1)->format('M Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                <flux:badge size="sm" :color="match($loan->status) {
                                    'active' => 'emerald',
                                    'paused' => 'amber',
                                    'completed' => 'indigo',
                                    default => 'zinc'
                                }">
                                    {{ ucfirst($loan->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:menu.item icon="clock" wire:click="openHistoryModal({{ $loan->id }})">
                                            {{ __('Repayment History') }}
                                        </flux:menu.item>
                                        @can('payroll.manage_loans')
                                            @if(in_array($loan->status, ['active', 'paused']))
                                                <flux:menu.item icon="banknotes" wire:click="openRepaymentModal({{ $loan->id }})">
                                                    {{ __('Record Manual Repayment') }}
                                                </flux:menu.item>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $loan->id }})">
                                                    {{ __('Edit Loan') }}
                                                </flux:menu.item>
                                                <flux:menu.item :icon="$loan->status === 'active' ? 'pause' : 'play'" wire:click="togglePause({{ $loan->id }})">
                                                    {{ $loan->status === 'active' ? __('Pause Deductions') : __('Resume Deductions') }}
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="x-circle" variant="danger" wire:click="cancelLoan({{ $loan->id }})" wire:confirm="{{ __('Cancel this loan? Remaining balance will stop deducting in future payroll runs.') }}">
                                                    {{ __('Cancel Loan') }}
                                                </flux:menu.item>
                                            @endif
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-12 text-center text-zinc-500">
                                {{ __('No staff loans found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    {{-- Create / Edit Loan Modal --}}
    <flux:modal wire:model.self="showLoanModal" class="max-w-lg w-full">
        <form wire:submit="saveLoan" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingLoanId ? __('Edit Staff Loan') : __('New Staff Loan') }}</flux:heading>
                <flux:subheading>{{ __('Loan installments are deducted automatically on payroll runs starting from the selected period.') }}</flux:subheading>
            </div>

            @if(! $editingLoanId)
                <flux:select wire:model="staff_id" :label="__('Staff Member')" required>
                    <flux:select.option value="">{{ __('Select staff...') }}</flux:select.option>
                    @foreach($staffList as $s)
                        <flux:select.option :value="$s->id">{{ $s->first_name }} {{ $s->last_name }} ({{ $s->staff_number }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="staff_id" />
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:input wire:model="title" :label="__('Loan Title')" :placeholder="__('e.g. Salary Advance')" required />
                <flux:input wire:model="reference_no" :label="__('Reference (Optional)')" :placeholder="__('LN-2026-001')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if(! $editingLoanId)
                    <div>
                        <flux:input type="number" step="0.01" min="1" wire:model="principal_amount" :label="__('Principal Amount (₦)')" required />
                        <flux:error name="principal_amount" />
                    </div>
                @endif
                <div class="{{ $editingLoanId ? 'sm:col-span-2' : '' }}">
                    <flux:input type="number" step="0.01" min="1" wire:model="monthly_deduction" :label="__('Monthly Deduction (₦)')" required />
                    <flux:error name="monthly_deduction" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="start_month" :label="__('Start Month')">
                    @for($m = 1; $m <= 12; $m++)
                        <flux:select.option :value="$m">{{ Carbon::createFromDate(2026, $m, 1)->format('F') }}</flux:select.option>
                    @endfor
                </flux:select>
                <flux:input type="number" min="2020" max="2100" wire:model="start_year" :label="__('Start Year')" required />
            </div>

            <flux:textarea wire:model="notes" :label="__('Notes (Optional)')" rows="2" />

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ $editingLoanId ? __('Update Loan') : __('Create Loan') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Record Manual Repayment Modal --}}
    <flux:modal wire:model.self="showRepaymentModal" class="max-w-md w-full">
        @if($repayingLoan)
            <form wire:submit="recordManualRepayment" class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Record Manual Repayment') }}</flux:heading>
                    <flux:subheading>
                        {{ $repayingLoan->title }} &bull; {{ __('Remaining:') }} ₦{{ number_format((float) $repayingLoan->remaining_balance, 2) }}
                    </flux:subheading>
                </div>

                <div>
                    <flux:input type="number" step="0.01" min="0.01" wire:model="repayment_amount" :label="__('Amount Paid (₦)')" required />
                    <flux:error name="repayment_amount" />
                </div>

                <flux:input wire:model="repayment_notes" :label="__('Notes (Optional)')" :placeholder="__('Cash / Bank transfer ref')" />

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        {{ __('Record Repayment') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Repayment History Modal --}}
    <flux:modal wire:model.self="showHistoryModal" class="max-w-2xl w-full">
        @if($historyLoan)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $historyLoan->title }} — {{ __('Repayment History') }}</flux:heading>
                    <flux:subheading>
                        {{ $historyLoan->staff?->first_name }} {{ $historyLoan->staff?->last_name }}
                        &bull; {{ __('Paid:') }} ₦{{ number_format((float) $historyLoan->total_repaid, 2) }}
                        &bull; {{ __('Remaining:') }} ₦{{ number_format((float) $historyLoan->remaining_balance, 2) }}
                    </flux:subheading>
                </div>

                <div class="relative overflow-x-auto max-h-80">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Source') }}</flux:table.column>
                            <flux:table.column class="text-right">{{ __('Amount') }}</flux:table.column>
                            <flux:table.column class="text-right">{{ __('Balance After') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($historyLoan->repayments as $rep)
                                <flux:table.row :key="$rep->id">
                                    <flux:table.cell class="text-xs">{{ $rep->paid_at?->format('M d, Y') }}</flux:table.cell>
                                    <flux:table.cell class="text-xs">
                                        <flux:badge size="sm" :color="$rep->payment_source === 'payroll' ? 'indigo' : 'emerald'">
                                            {{ ucfirst($rep->payment_source) }}
                                        </flux:badge>
                                        @if($rep->notes)
                                            <div class="text-zinc-500 mt-0.5">{{ $rep->notes }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right font-mono text-xs font-semibold">
                                        ₦{{ number_format((float) $rep->amount, 2) }}
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right font-mono text-xs">
                                        ₦{{ number_format((float) $rep->balance_after, 2) }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">
                                        {{ __('No repayments recorded yet.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
