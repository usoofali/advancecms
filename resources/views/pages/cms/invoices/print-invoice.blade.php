<?php

use App\Models\StudentInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Print Official Invoice')] class extends Component
{
    public StudentInvoice $studentInvoice;

    public function mount(StudentInvoice $studentInvoice)
    {
        $this->studentInvoice = $studentInvoice->load(['student', 'applicant', 'invoice.items', 'institution']);
    }
};
?>

<div class="p-4 bg-white min-h-screen">
    <div class="max-w-3xl mx-auto border-2 border-zinc-200 p-6 rounded-lg shadow-sm">
        <div class="flex flex-col items-center mb-2 border-b-2 border-black pb-2">
            @if($studentInvoice->institution?->logo_url)
            <img src="{{ $studentInvoice->institution->logo_url }}" alt="{{ $studentInvoice->institution->name }} Logo"
                class="h-16 mb-2 object-contain">
            @endif
            <h1 class="text-2xl font-bold uppercase">{{ $studentInvoice->institution->name ?? config('app.name') }}</h1>
            <p class="text-[10px] text-zinc-600 uppercase text-center max-w-md">{{ $studentInvoice->institution->address
                }}</p>
            <p class="text-[10px] text-zinc-600 uppercase font-bold mt-1">
                @if($studentInvoice->institution->email) EMAIL: {{ $studentInvoice->institution->email }} @endif
                @if($studentInvoice->institution->phone) | TEL: {{ $studentInvoice->institution->phone }} @endif
            </p>
            <h2 class="text-sm font-bold uppercase tracking-widest mt-4">Official Student Invoice</h2>
            <div class="mt-4 flex justify-between w-full text-sm font-bold border-t border-zinc-100 pt-2">
                <span class="text-zinc-600 uppercase">Invoice No: <span class="text-black">#INV-{{ $studentInvoice->id
                        }}</span></span>
                <span class="text-zinc-600 uppercase">Date Issued: <span class="text-black">{{
                        $studentInvoice->created_at?->format('d/m/Y') }}</span></span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-1 mb-2">
            <div class="space-y-1">
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Name:</span>
                    <span class="flex-1 uppercase font-bold text-base">
                        @if($studentInvoice->student)
                            {{ $studentInvoice->student->first_name }} {{ $studentInvoice->student->last_name }}
                        @else
                            {{ $studentInvoice->applicant->full_name }}
                        @endif
                    </span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">
                        {{ $studentInvoice->student ? 'Matric Number' : 'Application No' }}:
                    </span>
                    <span class="flex-1 uppercase font-mono font-bold text-sm">
                        {{ $studentInvoice->student ? $studentInvoice->student->matric_number : $studentInvoice->applicant->application_number }}
                    </span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Program:</span>
                    <span class="flex-1 uppercase font-semibold text-sm">
                        {{ $studentInvoice->student ? $studentInvoice->student->program?->name : $studentInvoice->applicant->program?->name }}
                    </span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Level / Status:</span>
                    <span class="flex-1 uppercase font-bold text-sm">
                        @if($studentInvoice->student)
                            {{ $studentInvoice->student->currentLevel($studentInvoice->invoice->academicSession) }} LEVEL
                        @else
                            ADMISSION APPLICANT
                        @endif
                    </span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Invoice
                        Status:</span>
                    <span
                        class="flex-1 uppercase font-bold text-sm {{ $studentInvoice->status === 'paid' ? 'text-green-600' : ($studentInvoice->status === 'cancelled' ? 'text-red-600' : 'text-orange-600') }}">
                        {{ $studentInvoice->status }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mb-2">
            <h3 class="text-xs font-bold uppercase tracking-widest text-zinc-400 mb-2">Invoice Items</h3>
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-zinc-200">
                        <th class="py-2 text-xs font-bold uppercase">Item Description</th>
                        <th class="py-2 text-right text-xs font-bold uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($studentInvoice->invoice->items as $item)
                    <tr>
                        <td class="py-1 text-sm font-medium">{{ $item->item_name }}</td>
                        <td class="py-1 text-right font-medium text-zinc-900">₦{{ number_format($item->amount, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php $actualTotal = $studentInvoice->invoice->items->sum('amount'); @endphp
                    <tr class="border-t-2 border-zinc-200 font-bold">
                        <td class="py-4 text-xs uppercase tracking-widest">Total Amount Payable</td>
                        <td class="py-4 text-right text-lg">₦{{ number_format($actualTotal, 2)
                            }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($studentInvoice->invoice->account_name || $studentInvoice->invoice->account_number ||
        $studentInvoice->invoice->bank_name)
        <div class="mb-2 p-3 bg-zinc-50 border border-zinc-200 rounded">
            <h3 class="text-[10px] font-bold uppercase tracking-widest text-zinc-500 mb-2">Payment Information (Bank
                Transfer)</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <span class="block text-[9px] text-zinc-400 uppercase font-bold">Bank Name</span>
                    <span class="text-xs font-semibold">{{ $studentInvoice->invoice->bank_name }}</span>
                </div>
                <div>
                    <span class="block text-[9px] text-zinc-400 uppercase font-bold">Account Name</span>
                    <span class="text-xs font-semibold">{{ $studentInvoice->invoice->account_name }}</span>
                </div>
                <div>
                    <span class="block text-[9px] text-zinc-400 uppercase font-bold">Account Number</span>
                    <span class="text-sm font-mono font-bold">{{ $studentInvoice->invoice->account_number }}</span>
                </div>
            </div>
        </div>
        @endif

        <div class="mt-3 pt-4 text-center border-t border-zinc-100">
            <p class="text-xs text-zinc-500 italic mb-4">This is an official document generated by the institution.
                Verification required for registration.</p>

            <div class="flex justify-between items-end px-4">
                <div class="text-center">
                    <div class="w-32 border-b border-black mb-1 italic text-xs">Official Stamp</div>
                    <span class="text-[10px] text-zinc-400 uppercase tracking-tighter">Academic Registry</span>
                </div>

                <div class="flex flex-col items-center">
                    @php
                    $identifier = $studentInvoice->student ? $studentInvoice->student->matric_number : $studentInvoice->applicant->application_number;
                    $qrData = "Invoice: #INV-{$studentInvoice->id}\n"
                    . "Holder: {$identifier}\n"
                    . "Total: ₦" . number_format($studentInvoice->total_amount, 2) . "\n"
                    . "Status: {$studentInvoice->status}";
                    @endphp
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}"
                        alt="Verification QR Code" class="size-20 border border-zinc-100 p-1 bg-white">
                    <span class="text-[9px] text-zinc-400 uppercase tracking-tighter mt-1">Scan to Verify</span>
                </div>

                <div class="text-right">
                    <div class="text-[10px] text-zinc-400 uppercase tracking-tighter">Issue Timestamp</div>
                    <div class="text-xs font-mono">{{ $studentInvoice->created_at->format('Y-m-d H:i:s') }}</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
                margin: 0;
                background: white;
            }

            .min-h-screen {
                min-height: 0;
            }

            .max-w-3xl {
                max-width: 100%;
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 0;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>

    <div class="mt-10 no-print flex justify-center gap-4">
        <flux:button variant="primary" icon="printer" onclick="window.print()">Print This Invoice</flux:button>
        
        @auth
            <flux:button variant="ghost" icon="arrow-left" href="{{ url()->previous() }}">Go Back</flux:button>
        @else
            @if($studentInvoice->applicant)
                <flux:button variant="ghost" icon="arrow-left" href="{{ route('applicant.portal', $studentInvoice->applicant->application_number) }}">Back to Portal</flux:button>
            @endif
        @endauth
    </div>
</div>