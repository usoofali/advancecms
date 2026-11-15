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

        $this->run = $run->load('institution');
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

    {{-- Print Header --}}
    <div class="hidden print:block text-center border-b border-gray-300 pb-4 mb-4">
        <h1 class="text-xl font-bold uppercase">{{ $run->institution?->name ?? config('app.name') }}</h1>
        <h2 class="text-sm font-semibold text-gray-700 mt-1">DEPARTMENTAL EXPENDITURE SUMMARY — {{ strtoupper($run->title) }}</h2>
        <p class="text-xs text-gray-500 mt-1">Printed: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    {{-- Summary Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Department / Unit') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Staff') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Base Pay') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Allowances') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Gross Pay') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Deductions') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                @forelse($departmentsSummary as $row)
                    <tr>
                        <td class="px-4 py-3.5 font-semibold text-zinc-900 dark:text-zinc-100">{{ $row['department'] }}</td>
                        <td class="px-4 py-3.5 text-center">{{ $row['staff_count'] }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs">₦{{ number_format($row['total_base'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400">₦{{ number_format($row['total_allowances'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs">₦{{ number_format($row['total_gross'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs text-red-600 dark:text-red-400">₦{{ number_format($row['total_deductions'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-bold text-zinc-900 dark:text-white">₦{{ number_format($row['total_net'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-zinc-500">{{ __('No data available.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-700 font-bold text-sm">
                <tr>
                    <td class="px-4 py-3">{{ __('Grand Total') }}</td>
                    <td class="px-4 py-3 text-center">{{ $departmentsSummary->sum('staff_count') }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">₦{{ number_format($departmentsSummary->sum('total_base'), 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">₦{{ number_format($departmentsSummary->sum('total_allowances'), 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">₦{{ number_format($departmentsSummary->sum('total_gross'), 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-xs">₦{{ number_format($departmentsSummary->sum('total_deductions'), 2) }}</td>
                    <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">₦{{ number_format($departmentsSummary->sum('total_net'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
