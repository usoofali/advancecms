<?php

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] #[Title('Bank Payment Schedule')] class extends Component
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

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('payroll.reports');

        $items = PayrollItem::with('staff')
            ->where('payroll_run_id', $this->run->id)
            ->where('net_pay', '>', 0)
            ->orderBy('id')
            ->get();

        ActivityLogger::log(
            action: 'exported_bank_schedule',
            module: 'Payroll',
            description: "Exported bank payment schedule CSV for {$this->run->title}",
            subject: $this->run,
            institutionId: $this->run->institution_id
        );

        $filename = 'bank-schedule-'.$this->run->year.'-'.str_pad((string) $this->run->month, 2, '0', STR_PAD_LEFT).'.csv';

        return response()->streamDownload(function () use ($items): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['S/N', 'Staff ID', 'Staff Name', 'Bank Name', 'Account Number', 'Account Name', 'Net Amount']);

            foreach ($items as $idx => $item) {
                fputcsv($handle, [
                    $idx + 1,
                    $item->staff?->staff_number,
                    trim("{$item->staff?->first_name} {$item->staff?->last_name}"),
                    $item->bank_name ?: '—',
                    $item->account_number ?: '—',
                    $item->account_name ?: trim("{$item->staff?->first_name} {$item->staff?->last_name}"),
                    number_format((float) $item->net_pay, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function with(): array
    {
        $items = PayrollItem::with('staff')
            ->where('payroll_run_id', $this->run->id)
            ->where('net_pay', '>', 0)
            ->orderBy('id')
            ->get();

        return [
            'items' => $items,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Bank Payment Schedule') }}</flux:heading>
            <flux:subheading>{{ $run->title }} &bull; {{ $run->institution?->name }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="ghost" icon="arrow-left" :href="route('cms.payroll.runs', ['run_id' => $run->id])" wire:navigate>
                {{ __('Back to Run') }}
            </flux:button>
            <flux:button variant="ghost" icon="arrow-down-tray" wire:click="exportCsv">
                {{ __('Export CSV') }}
            </flux:button>
            <flux:button variant="primary" icon="printer" onclick="window.print()">
                {{ __('Print Schedule') }}
            </flux:button>
        </div>
    </div>

    {{-- Print Header --}}
    <div class="hidden print:block text-center border-b border-gray-300 pb-4 mb-4">
        <h1 class="text-xl font-bold uppercase">{{ $run->institution?->name ?? config('app.name') }}</h1>
        <h2 class="text-sm font-semibold text-gray-700 mt-1">BANK PAYMENT SCHEDULE — {{ strtoupper($run->title) }}</h2>
        <p class="text-xs text-gray-500 mt-1">Total Payout: ₦{{ number_format((float) $run->total_net, 2) }} &bull; Printed: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    {{-- Schedule Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">{{ __('Staff Name') }}</th>
                    <th class="px-4 py-3">{{ __('Bank Name') }}</th>
                    <th class="px-4 py-3">{{ __('Account Number') }}</th>
                    <th class="px-4 py-3">{{ __('Account Name') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Net Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                @forelse($items as $index => $item)
                    <tr>
                        <td class="px-4 py-3 text-xs text-zinc-500">{{ $index + 1 }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->staff?->first_name }} {{ $item->staff?->last_name }}</div>
                            <div class="text-xs font-mono text-zinc-500">{{ $item->staff?->staff_number }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $item->bank_name ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $item->account_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ $item->account_name ?: trim("{$item->staff?->first_name} {$item->staff?->last_name}") }}</td>
                        <td class="px-4 py-3 text-right font-bold text-zinc-900 dark:text-white">₦{{ number_format((float) $item->net_pay, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-zinc-500">{{ __('No payable staff records found in this run.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-700 font-bold text-sm">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right">{{ __('Total Net Disbursement:') }}</td>
                    <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">₦{{ number_format((float) $items->sum('net_pay'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
