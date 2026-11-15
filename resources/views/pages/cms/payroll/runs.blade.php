<?php

use App\Models\Institution;
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

new #[Layout('layouts.app')] #[Title('Monthly Payroll Runs')] class extends Component
{
    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public ?int $run_id = null;

    #[Url]
    public string $search = '';

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
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: Institution::where('status', 'active')->value('id'))
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

        if ($existing && in_array($existing->status, ['approved', 'paid'])) {
            $this->addError('gen_month', __('This period is already approved/paid and cannot be overwritten.'));

            return;
        }

        $run = $calculator->generateOrRecalculateRun(
            $institution,
            $this->gen_month,
            $this->gen_year,
            auth()->user(),
            $this->gen_title ?: null
        );

        $this->run_id = $run->id;
        $this->showGenerateModal = false;
        $this->dispatch('notify', title: __('Payroll run calculated successfully.'));
    }

    public function recalculateSelectedRun(PayrollCalculator $calculator): void
    {
        Gate::authorize('payroll.create_run');
        $instId = $this->resolveInstitutionId();
        $run = PayrollRun::where('institution_id', $instId)->findOrFail($this->run_id);

        if ($run->status !== 'draft') {
            return;
        }

        $calculator->generateOrRecalculateRun(
            Institution::findOrFail($instId),
            $run->month,
            $run->year,
            auth()->user(),
            $run->title
        );

        $this->dispatch('notify', title: __('Draft payroll recalculated.'));
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

        $this->dispatch('notify', title: __('Submitted for administrative approval.'));
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

        $this->dispatch('notify', title: __('Payroll run approved and finalized.'));
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

        $this->dispatch('notify', title: __('Payroll marked as paid.'));
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
        $this->dispatch('notify', title: __('Adjustment saved.'));
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
                ->get()
            : collect();

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'items' => $items,
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
                            <flux:button size="sm" variant="primary" icon="check-badge" wire:click="approveRun" wire:confirm="{{ __('Approve and finalize this payroll run?') }}">
                                {{ __('Approve Payroll') }}
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
        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Staff') }}</th>
                        <th class="px-4 py-3">{{ __('Pay Basis') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Base Pay') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Allowances') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Deductions') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Net Pay') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                    @forelse($items as $item)
                        <tr wire:key="run-item-{{ $item->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-900/20">
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->staff?->first_name }} {{ $item->staff?->last_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $item->category?->name ?? __('Default') }} {{ $item->department ? '• '.$item->department->name : '' }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-xs">
                                @if($item->pay_type === 'fixed')
                                    <flux:badge size="sm" color="emerald">{{ __('Fixed') }}</flux:badge>
                                @else
                                    <div class="font-medium text-amber-700 dark:text-amber-300">{{ $item->contacts_count }} {{ __('contacts') }} &bull; {{ (float) $item->credit_units_total }} {{ __('units') }}</div>
                                    <div class="text-zinc-500">@ ₦{{ number_format((float) $item->rate_used, 2) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-xs">₦{{ number_format((float) $item->base_pay, 2) }}</td>
                            <td class="px-4 py-3.5 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                +₦{{ number_format((float) $item->total_allowances + max(0, (float) $item->manual_adjustment), 2) }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-xs text-red-600 dark:text-red-400">
                                -₦{{ number_format((float) $item->total_deductions, 2) }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-bold text-zinc-900 dark:text-white">
                                ₦{{ number_format((float) $item->net_pay, 2) }}
                                @if($item->adjustment_note)
                                    <div class="text-[10px] font-normal text-amber-600">{{ $item->adjustment_note }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                @if((!in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.create_run')) || (in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.approve')))
                                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="openAdjustModal({{ $item->id }})" title="{{ __('Adjust') }}" />
                                @endif
                                <flux:button size="xs" variant="ghost" icon="document-text" :href="route('cms.payroll.payslip', ['item' => $item->id])" target="_blank" title="{{ __('Payslip') }}" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-zinc-500">{{ __('No staff paylines in this run.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($items as $item)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item->staff?->first_name }} {{ $item->staff?->last_name }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $item->pay_type === 'fixed' ? __('Fixed Salary') : "{$item->contacts_count} contacts • {$item->credit_units_total} units" }}
                            </div>
                        </div>
                        <div class="text-right font-black text-emerald-600 dark:text-emerald-400 text-sm">
                            ₦{{ number_format((float) $item->net_pay, 2) }}
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                        <span class="text-zinc-500">{{ __('Base:') }} ₦{{ number_format((float) $item->base_pay, 2) }}</span>
                        <div class="flex items-center gap-1">
                            @if((!in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.create_run')) || (in_array($selectedRun->status, ['approved', 'paid']) && auth()->user()->can('payroll.approve')))
                                <flux:button size="xs" variant="ghost" wire:click="openAdjustModal({{ $item->id }})">{{ __('Adjust') }}</flux:button>
                            @endif
                            <flux:button size="xs" variant="ghost" :href="route('cms.payroll.payslip', ['item' => $item->id])" target="_blank">{{ __('Payslip') }}</flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                    {{ __('No staff paylines in this run.') }}
                </div>
            @endforelse
        </div>
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
