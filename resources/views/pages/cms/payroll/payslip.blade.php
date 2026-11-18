<?php

use App\Models\PayrollItem;
use App\Models\Staff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Payslips')] class extends Component
{
    public ?PayrollItem $selectedItem = null;

    public function mount(?PayrollItem $item = null): void
    {
        $user = auth()->user();

        if ($item && $item->exists) {
            $item->load(['run.institution', 'run.approver', 'staff', 'category', 'department']);

            $canViewAny = $user->can('payroll.view');
            $ownStaffId = Staff::where('email', $user->email)->value('id');
            $isOwn = $ownStaffId && (int) $item->staff_id === (int) $ownStaffId && in_array($item->run?->status, ['approved', 'paid']);

            if (! $user->hasRole('Super Admin') && (int) $item->institution_id !== (int) $user->institution_id) {
                abort(403);
            }

            if (! $canViewAny && ! $isOwn) {
                abort(403, 'You do not have permission to view this payslip.');
            }

            $this->selectedItem = $item;
        } else {
            if (! $user->can('payroll.view_own_payslip') && ! $user->can('payroll.view')) {
                abort(403);
            }
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $staff = Staff::where('email', $user->email)->first();

        $myPayslips = $staff
            ? PayrollItem::with(['run'])
                ->where('staff_id', $staff->id)
                ->whereHas('run', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
                ->orderByDesc('id')
                ->get()
            : collect();

        return [
            'myPayslips' => $myPayslips,
            'staff' => $staff,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if($selectedItem)
        {{-- Print Action Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 print:hidden">
            <div class="flex items-center gap-2">
                @if(auth()->user()->can('payroll.view'))
                    <flux:button variant="ghost" icon="arrow-left" :href="route('cms.payroll.runs', ['run_id' => $selectedItem->payroll_run_id])" wire:navigate>
                        {{ __('Back to Payroll Run') }}
                    </flux:button>
                @else
                    <flux:button variant="ghost" icon="arrow-left" :href="route('cms.payroll.my-payslips')" wire:navigate>
                        {{ __('All My Payslips') }}
                    </flux:button>
                @endif
            </div>

            <flux:button variant="primary" icon="printer" onclick="window.print()">
                {{ __('Print Payslip') }}
            </flux:button>
        </div>

        {{-- Printable Payslip Card --}}
        <div class="max-w-3xl mx-auto w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 sm:p-8 shadow-xs space-y-6 print:max-w-none print:border-none print:shadow-none print:p-0 print:bg-white print:text-black">
            {{-- Institution Header --}}
            <div class="text-center border-b border-zinc-200 dark:border-zinc-700 print:border-gray-300 pb-4">
                @if($selectedItem->run?->institution?->logo_url)
                    <img src="{{ $selectedItem->run->institution->logo_url }}" alt="Logo" class="mx-auto mb-2 h-14 w-auto object-contain" />
                @endif
                <h1 class="text-xl font-bold uppercase tracking-wide text-zinc-900 dark:text-white print:text-black">
                    {{ $selectedItem->run?->institution?->name ?? config('app.name') }}
                </h1>
                @if($selectedItem->run?->institution?->address)
                    <p class="text-xs text-zinc-500 print:text-gray-600 mt-0.5">{{ $selectedItem->run->institution->address }}</p>
                @endif
                <div class="mt-2 inline-block px-3 py-1 rounded bg-zinc-100 dark:bg-zinc-800 print:bg-gray-100 text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-200 print:text-gray-800">
                    {{ __('Official Employee Payslip') }} &mdash; {{ $selectedItem->run?->title }}
                </div>
                <p class="hidden print:block text-[10px] text-gray-500 mt-1">
                    {{ __('Printed:') }} {{ now()->format('F d, Y h:i A') }}
                </p>
            </div>

            {{-- Staff Metadata --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/60 print:bg-gray-50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 print:border-gray-300">
                <div class="space-y-1">
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Employee Name:') }}</span> <strong class="text-zinc-900 dark:text-white print:text-black">{{ $selectedItem->staff?->first_name }} {{ $selectedItem->staff?->last_name }}</strong></div>
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Staff ID:') }}</span> <strong class="font-mono print:text-black">{{ $selectedItem->staff?->staff_number }}</strong></div>
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Category:') }}</span> <strong class="print:text-black">{{ $selectedItem->category?->name ?? __('Standard') }}</strong></div>
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Department:') }}</span> <strong class="print:text-black">{{ $selectedItem->department?->name ?? '—' }}</strong></div>
                </div>
                <div class="space-y-1">
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Pay Basis:') }}</span> <strong class="print:text-black">{{ $selectedItem->pay_type === 'fixed' ? __('Fixed Monthly Salary') : __('Contact / Credit Unit') }}</strong></div>
                    @if($selectedItem->pay_type !== 'fixed')
                        <div><span class="text-zinc-500 print:text-gray-600">{{ __('Contacts / Units:') }}</span> <strong class="print:text-black">{{ $selectedItem->contacts_count }} contacts &bull; {{ (float) $selectedItem->credit_units_total }} units</strong></div>
                    @endif
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Bank:') }}</span> <strong class="print:text-black">{{ $selectedItem->bank_name ?: '—' }}</strong></div>
                    <div><span class="text-zinc-500 print:text-gray-600">{{ __('Account No:') }}</span> <strong class="font-mono print:text-black">{{ $selectedItem->account_number ?: '—' }}</strong></div>
                </div>
            </div>

            {{-- Earnings vs Deductions --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                {{-- Earnings --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 border-b border-zinc-200 dark:border-zinc-700 pb-1.5">{{ __('Earnings') }}</h3>
                    <div class="flex justify-between py-1">
                        <span>{{ __('Base Pay') }}</span>
                        <span class="font-mono font-semibold">₦{{ number_format((float) $selectedItem->base_pay, 2) }}</span>
                    </div>
                    @foreach($selectedItem->allowances_breakdown ?? [] as $alw)
                        <div class="flex justify-between py-1 text-zinc-600 dark:text-zinc-300">
                            <span>{{ $alw['name'] }}</span>
                            <span class="font-mono">₦{{ number_format((float) $alw['amount'], 2) }}</span>
                        </div>
                    @endforeach
                    @if((float) $selectedItem->manual_adjustment > 0)
                        <div class="flex justify-between py-1 text-emerald-600 dark:text-emerald-400">
                            <span>{{ $selectedItem->adjustment_note ?: __('Bonus / Addition') }}</span>
                            <span class="font-mono">₦{{ number_format((float) $selectedItem->manual_adjustment, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700 font-bold">
                        <span>{{ __('Total Gross Earnings') }}</span>
                        <span class="font-mono">₦{{ number_format((float) $selectedItem->gross_pay, 2) }}</span>
                    </div>
                </div>

                {{-- Deductions --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400 border-b border-zinc-200 dark:border-zinc-700 pb-1.5">{{ __('Deductions') }}</h3>
                    @forelse($selectedItem->deductions_breakdown ?? [] as $ded)
                        <div class="flex justify-between py-1 text-zinc-600 dark:text-zinc-300">
                            <span>{{ $ded['name'] }}</span>
                            <span class="font-mono">₦{{ number_format((float) $ded['amount'], 2) }}</span>
                        </div>
                    @empty
                        @if((float) $selectedItem->manual_adjustment >= 0)
                            <div class="py-1 text-xs text-zinc-400 italic">{{ __('No deductions applied') }}</div>
                        @endif
                    @endforelse
                    @if((float) $selectedItem->manual_adjustment < 0)
                        <div class="flex justify-between py-1 text-red-600 dark:text-red-400">
                            <span>{{ $selectedItem->adjustment_note ?: __('Adjustment Deduction') }}</span>
                            <span class="font-mono">₦{{ number_format(abs((float) $selectedItem->manual_adjustment), 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700 font-bold">
                        <span>{{ __('Total Deductions') }}</span>
                        <span class="font-mono text-red-600 dark:text-red-400">₦{{ number_format((float) $selectedItem->total_deductions, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Net Pay Box --}}
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Net Take-Home Pay') }}</div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Status:') }} {{ __($selectedItem->run?->statusLabel() ?? '') }}</div>
                </div>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 font-mono">
                    ₦{{ number_format((float) $selectedItem->net_pay, 2) }}
                </div>
            </div>

            {{-- Footer Sign-off --}}
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-between text-[11px] text-zinc-500">
                <span>{{ __('Approved by:') }} {{ $selectedItem->run?->approver?->name ?? __('Pending Approval') }}</span>
                <span>{{ __('Generated:') }} {{ now()->format('M d, Y') }}</span>
            </div>
        </div>
    @else
        {{-- My Payslips List --}}
        <div>
            <flux:heading size="xl">{{ __('My Payslips') }}</flux:heading>
            <flux:subheading>{{ __('View and print your approved monthly payslips') }}</flux:subheading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($myPayslips as $slip)
                <div wire:key="my-slip-{{ $slip->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $slip->run?->title }}</div>
                            <div class="text-xs text-zinc-500">{{ $slip->run?->approved_at?->format('M d, Y') }}</div>
                        </div>
                        <flux:badge size="sm" :color="$slip->run?->statusColor() ?? 'zinc'">{{ __($slip->run?->statusLabel() ?? '') }}</flux:badge>
                    </div>

                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700 flex items-center justify-between">
                        <div>
                            <span class="text-xs text-zinc-500 block">{{ __('Net Pay') }}</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $slip->net_pay, 2) }}</span>
                        </div>
                        <flux:button size="xs" variant="primary" icon="document-text" :href="route('cms.payroll.payslip', ['item' => $slip->id])" wire:navigate>
                            {{ __('View Slip') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center text-zinc-500">
                    {{ __('No approved payslips available yet.') }}
                </div>
            @endforelse
        </div>
    @endif
</div>
