<?php

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Departmental Payroll Summary')] class extends Component
{
    public PayrollRun $run;

    public function mount(PayrollRun $run): void
    {
        Gate::authorize('payroll.reports');

        $user = auth()->user();
        if (! $user->hasRole('Super Admin') && (int) $run->institution_id !== (int) $user->institution_id) {
            abort(403, 'You do not have permission to view this payroll run.');
        }

        $this->run = $run->load(['institution', 'preparer', 'approver']);
    }

    public function with(): array
    {
        $items = PayrollItem::with('department')
            ->where('payroll_run_id', $this->run->id)
            ->get();

        $departmentsSummary = $items->groupBy(fn (PayrollItem $item) => $item->department?->name ?? 'General / Administration')
            ->map(function ($group, $deptName) {
                return [
                    'department' => $deptName,
                    'staff_count' => $group->count(),
                    'total_base' => (float) $group->sum('base_pay'),
                    'total_allowances' => (float) $group->sum('total_allowances'),
                    'total_gross' => (float) $group->sum('gross_pay'),
                    'total_deductions' => (float) $group->sum('total_deductions'),
                    'total_net' => (float) $group->sum('net_pay'),
                ];
            })
            ->sortBy('department')
            ->values();

        return [
            'departmentsSummary' => $departmentsSummary,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Departmental Payroll Summary') }}</flux:heading>
            <flux:subheading>{{ $run->title }} &bull; {{ $run->institution?->name }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="ghost" icon="arrow-left" :href="route('cms.payroll.runs', ['run_id' => $run->id])" wire:navigate>
                {{ __('Back to Run') }}
            </flux:button>
            <flux:button variant="primary" icon="printer" onclick="window.print()">
                {{ __('Print Summary') }}
            </flux:button>
        </div>
    </div>

    {{-- Print Header & Metadata --}}
    <div class="hidden print:block space-y-4 mb-4 text-black">
        <div class="text-center border-b border-gray-300 pb-4">
            @if($run->institution?->logo_url)
                <img src="{{ $run->institution->logo_url }}" alt="Logo" class="mx-auto mb-2 h-14 w-auto object-contain" />
            @endif
            <h1 class="text-xl font-bold uppercase tracking-wide">{{ $run->institution?->name ?? config('app.name') }}</h1>
            @if($run->institution?->address)
                <p class="text-xs text-gray-600 mt-0.5">{{ $run->institution->address }}</p>
            @endif
            <h2 class="text-sm font-bold text-gray-800 mt-2 uppercase tracking-wider">{{ __('Departmental Payroll Summary') }}</h2>
            <p class="text-[11px] text-gray-500 mt-1">
                {{ __('Generated:') }} {{ now()->format('F d, Y h:i A') }} &bull; {{ __('Printed by:') }} {{ auth()->user()->name }}
            </p>
        </div>

        <div class="grid grid-cols-4 gap-3 text-xs p-3 bg-gray-50 border border-gray-200 rounded-lg">
            <div>
                <span class="font-bold block text-gray-500 uppercase text-[10px]">{{ __('Payroll Run') }}</span>
                <span class="font-semibold">{{ $run->title }}</span>
            </div>
            <div>
                <span class="font-bold block text-gray-500 uppercase text-[10px]">{{ __('Period / Status') }}</span>
                <span class="font-semibold">{{ \Carbon\Carbon::createFromDate($run->year, $run->month, 1)->format('F Y') }} ({{ __($run->statusLabel()) }})</span>
            </div>
            <div>
                <span class="font-bold block text-gray-500 uppercase text-[10px]">{{ __('Prepared / Approved By') }}</span>
                <span class="font-semibold">{{ $run->preparer?->name ?? '—' }}{{ $run->approver ? ' / '.$run->approver->name : '' }}</span>
            </div>
            <div>
                <span class="font-bold block text-gray-500 uppercase text-[10px]">{{ __('Departments / Net') }}</span>
                <span class="font-semibold">{{ $departmentsSummary->count() }} {{ __('units') }} &bull; ₦{{ number_format($departmentsSummary->sum('total_net'), 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Summary Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs print:border-gray-300 print:rounded-none print:shadow-none print:bg-white print:text-black">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500 print:bg-gray-100 print:text-gray-800 print:border-gray-300">
                <tr>
                    <th class="px-4 py-3 print:py-2">{{ __('Department / Unit') }}</th>
                    <th class="px-4 py-3 print:py-2 text-center">{{ __('Staff') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Base Pay') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Allowances') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Gross Pay') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Deductions') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Net Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm print:divide-gray-200">
                @forelse($departmentsSummary as $row)
                    <tr class="break-inside-avoid">
                        <td class="px-4 py-3.5 print:py-2 font-semibold text-zinc-900 dark:text-zinc-100 print:text-black">{{ $row['department'] }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-center print:text-black">{{ $row['staff_count'] }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($row['total_base'], 2) }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400 print:text-black">₦{{ number_format($row['total_allowances'], 2) }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($row['total_gross'], 2) }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-right font-mono text-xs text-red-600 dark:text-red-400 print:text-black">₦{{ number_format($row['total_deductions'], 2) }}</td>
                        <td class="px-4 py-3.5 print:py-2 text-right font-bold text-zinc-900 dark:text-white print:text-black">₦{{ number_format($row['total_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-zinc-500">{{ __('No data available.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-700 font-bold text-sm print:bg-gray-100 print:border-gray-300">
                <tr>
                    <td class="px-4 py-3 print:py-2 print:text-black">{{ __('Grand Total') }}</td>
                    <td class="px-4 py-3 print:py-2 text-center print:text-black">{{ $departmentsSummary->sum('staff_count') }}</td>
                    <td class="px-4 py-3 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($departmentsSummary->sum('total_base'), 2) }}</td>
                    <td class="px-4 py-3 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($departmentsSummary->sum('total_allowances'), 2) }}</td>
                    <td class="px-4 py-3 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($departmentsSummary->sum('total_gross'), 2) }}</td>
                    <td class="px-4 py-3 print:py-2 text-right font-mono text-xs print:text-black">₦{{ number_format($departmentsSummary->sum('total_deductions'), 2) }}</td>
                    <td class="px-4 py-3 print:py-2 text-right text-emerald-600 dark:text-emerald-400 print:text-black">₦{{ number_format($departmentsSummary->sum('total_net'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Print Signatories --}}
    <div class="hidden print:grid grid-cols-3 gap-8 mt-12 pt-4 text-xs text-black break-inside-avoid">
        <div class="text-center">
            <div class="border-t border-gray-400 pt-1.5 font-semibold">{{ __('Prepared By (Payroll Officer)') }}</div>
            <div class="text-[10px] text-gray-500 mt-0.5">{{ $run->preparer?->name ?? '________________________' }}</div>
        </div>
        <div class="text-center">
            <div class="border-t border-gray-400 pt-1.5 font-semibold">{{ __('Verified By (Bursar / Accountant)') }}</div>
            <div class="text-[10px] text-gray-500 mt-0.5">{{ __('Signature & Date') }}</div>
        </div>
        <div class="text-center">
            <div class="border-t border-gray-400 pt-1.5 font-semibold">{{ __('Approved By (Head of Institution)') }}</div>
            <div class="text-[10px] text-gray-500 mt-0.5">{{ $run->approver?->name ?? __('Signature & Date') }}</div>
        </div>
    </div>
</div>
