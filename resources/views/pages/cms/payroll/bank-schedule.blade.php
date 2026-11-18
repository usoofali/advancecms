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

        $this->run = $run->load(['institution', 'preparer', 'approver']);
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
            <h2 class="text-sm font-bold text-gray-800 mt-2 uppercase tracking-wider">{{ __('Official Bank Payment Schedule') }}</h2>
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
                <span class="font-bold block text-gray-500 uppercase text-[10px]">{{ __('Payable Staff / Total') }}</span>
                <span class="font-semibold">{{ $items->count() }} {{ __('staff') }} &bull; ₦{{ number_format((float) $items->sum('net_pay'), 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Schedule Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs print:border-gray-300 print:rounded-none print:shadow-none print:bg-white print:text-black">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500 print:bg-gray-100 print:text-gray-800 print:border-gray-300">
                <tr>
                    <th class="px-4 py-3 print:py-2">#</th>
                    <th class="px-4 py-3 print:py-2">{{ __('Staff Name') }}</th>
                    <th class="px-4 py-3 print:py-2">{{ __('Bank Name') }}</th>
                    <th class="px-4 py-3 print:py-2">{{ __('Account Number') }}</th>
                    <th class="px-4 py-3 print:py-2">{{ __('Account Name') }}</th>
                    <th class="px-4 py-3 print:py-2 text-right">{{ __('Net Amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm print:divide-gray-200">
                @forelse($items as $index => $item)
                    <tr class="break-inside-avoid">
                        <td class="px-4 py-3 print:py-2 text-xs text-zinc-500 print:text-gray-700">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 print:py-2">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 print:text-black">{{ $item->staff?->first_name }} {{ $item->staff?->last_name }}</div>
                            <div class="text-xs font-mono text-zinc-500 print:text-gray-600">{{ $item->staff?->staff_number }}</div>
                        </td>
                        <td class="px-4 py-3 print:py-2 text-xs print:text-black">{{ $item->bank_name ?: '—' }}</td>
                        <td class="px-4 py-3 print:py-2 font-mono text-xs print:text-black">{{ $item->account_number ?: '—' }}</td>
                        <td class="px-4 py-3 print:py-2 text-xs print:text-black">{{ $item->account_name ?: trim("{$item->staff?->first_name} {$item->staff?->last_name}") }}</td>
                        <td class="px-4 py-3 print:py-2 text-right font-bold text-zinc-900 dark:text-white print:text-black">₦{{ number_format((float) $item->net_pay, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-zinc-500">{{ __('No payable staff records found in this run.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-zinc-50 dark:bg-zinc-900/50 border-t border-zinc-200 dark:border-zinc-700 font-bold text-sm print:bg-gray-100 print:border-gray-300">
                <tr>
                    <td colspan="5" class="px-4 py-3 print:py-2 text-right print:text-black">{{ __('Total Net Disbursement:') }}</td>
                    <td class="px-4 py-3 print:py-2 text-right text-emerald-600 dark:text-emerald-400 print:text-black">₦{{ number_format((float) $items->sum('net_pay'), 2) }}</td>
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
