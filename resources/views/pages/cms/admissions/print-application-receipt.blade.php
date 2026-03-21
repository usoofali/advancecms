<?php

use App\Models\Receipt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Print Application Receipt')] class extends Component
{
    public Receipt $receipt;

    public function mount(Receipt $receipt)
    {
        $this->receipt = $receipt->load(['payment.applicant.program', 'payment.applicant.institution', 'institution']);
    }
};
?>

<div class="p-4 bg-white min-h-screen">
    <div class="max-w-3xl mx-auto border-2 border-zinc-200 p-6 rounded-lg shadow-sm">
        <div class="flex flex-col items-center mb-4 border-b-2 border-black pb-4">
            @if($receipt->institution?->logo_url)
            <img src="{{ $receipt->institution->logo_url }}" alt="{{ $receipt->institution->name }} Logo"
                class="h-16 mb-2 object-contain">
            @endif
            <h1 class="text-2xl font-bold uppercase">{{ $receipt->institution->name ?? config('app.name') }}</h1>
            <p class="text-[10px] text-zinc-600 uppercase text-center max-w-md leading-relaxed">{{ $receipt->institution->address }}</p>
            <p class="text-[10px] text-zinc-600 uppercase font-bold mt-1">
                @if($receipt->institution->email) EMAIL: {{ $receipt->institution->email }} @endif
                @if($receipt->institution->phone) | TEL: {{ $receipt->institution->phone }} @endif
            </p>
            <h2 class="text-xl font-bold uppercase tracking-widest mt-4">Application Payment Receipt</h2>
            <div class="mt-4 flex justify-between w-full text-sm font-bold border-t border-zinc-100 pt-2">
                <span class="text-zinc-600 uppercase">Receipt No: <span class="text-black">{{ $receipt->receipt_number }}</span></span>
                <span class="text-zinc-600 uppercase">Date: <span class="text-black">{{ $receipt->issued_at->format('d/m/Y') }}</span></span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-1 mb-4">
            <div class="space-y-1">
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Received From:</span>
                    <span class="flex-1 uppercase font-bold text-base">{{ $receipt->payment?->applicant?->full_name }}</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Application No:</span>
                    <span class="flex-1 uppercase font-mono font-bold text-sm">{{ $receipt->payment?->applicant?->application_number }}</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Program:</span>
                    <span class="flex-1 uppercase font-semibold text-sm">{{ $receipt->payment?->applicant?->program?->name }}</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Payment For:</span>
                    <span class="flex-1 uppercase font-semibold text-zinc-800 text-sm">Application Form Purchase</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Amount Paid:</span>
                    <span class="flex-1 text-xl font-black italic">₦{{ number_format($receipt->payment?->amount_paid, 2) }}</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Payment Method:</span>
                    <span class="flex-1 uppercase font-semibold text-zinc-700 text-xs">{{ str_replace('_', ' ', strtoupper($receipt->payment?->payment_method)) }}</span>
                </div>
                <div class="flex border-b border-zinc-100 pb-1">
                    <span class="w-36 text-zinc-500 uppercase text-[10px] font-bold tracking-tight">Reference:</span>
                    <span class="flex-1 font-mono text-zinc-700 uppercase text-xs">{{ $receipt->payment?->reference }}</span>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 text-center border-t border-zinc-100">
            <p class="text-xs text-zinc-500 italic mb-4">This is an electronically generated receipt. No physical signature is required.</p>
            
            <div class="flex justify-between items-end px-4">
                <div class="text-center">
                    <div class="w-32 border-b border-black mb-1 italic text-xs">Digitally Signed</div>
                    <span class="text-[10px] text-zinc-400 uppercase tracking-tighter">Admissions Officer</span>
                </div>

                <div class="flex flex-col items-center">
                    @php
                        $qrData = "Receipt: {$receipt->receipt_number}\n"
                                . "Applicant: {$receipt->payment->applicant->application_number}\n"
                                . "Amount: ₦" . number_format($receipt->payment->amount_paid, 2) . "\n"
                                . "Date: " . $receipt->issued_at->format('Y-m-d');
                    @endphp
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($qrData) }}" 
                         alt="Verification QR Code" 
                         class="size-20 border border-zinc-100 p-1 bg-white"
                    >
                    <span class="text-[9px] text-zinc-400 uppercase tracking-tighter mt-1">Scan to Verify</span>
                </div>
                
                <div class="text-right">
                    <div class="text-[10px] text-zinc-400 uppercase tracking-tighter">Issue Timestamp</div>
                    <div class="text-xs font-mono">{{ $receipt->issued_at->format('Y-m-d H:i:s') }}</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; background: white; }
            .min-h-screen { min-height: 0; }
            .max-w-3xl { max-width: 100%; border: none; box-shadow: none; margin: 0; padding: 0; }
            @page { margin: 1cm; }
        }
    </style>

    <div class="mt-10 no-print flex justify-center gap-4">
        <flux:button variant="primary" icon="printer" onclick="window.print()">Print Receipt</flux:button>
        <flux:button variant="ghost" onclick="window.history.back()">Go Back</flux:button>
    </div>
</div>
