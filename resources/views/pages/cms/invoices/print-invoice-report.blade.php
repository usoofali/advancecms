<?php

use App\Models\Invoice;
use App\Models\StudentInvoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;

new #[Layout('layouts.guest')] #[Title('Invoice Report')] class extends Component
{
    public Invoice $invoice;
    public ?App\Models\Institution $institution = null;
    public string $search = '';
    public string $status = 'all';
    public string $level = 'all';

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->search = request()->query('search', '');
        $this->status = request()->query('status', 'all');
        $this->level = request()->query('level', 'all');
        
        if ($institutionId = request()->query('institution_id')) {
            $this->institution = App\Models\Institution::find($institutionId);
        }
    }

    public function studentInvoices()
    {
        $sessionYear = (int) explode('/', $this->invoice->academicSession->name)[0];

        return $this->invoice->studentInvoices()
            ->with(['student', 'student.program', 'applicant', 'applicant.program', 'invoice', 'invoice.academicSession'])
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%')
                            ->orWhere('matric_number', 'like', '%'.$this->search.'%');
                    })->orWhereHas('applicant', function ($aq) {
                        $aq->where('full_name', 'like', '%'.$this->search.'%')
                            ->orWhere('application_number', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->level !== 'all', function ($q) use ($sessionYear) {
                $q->where(function ($qq) use ($sessionYear) {
                    $qq->whereHas('student', function ($sq) use ($sessionYear) {
                        $level = (int) $this->level;
                        $sq->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
                    })->orWhere(function ($aqq) {
                        if ($this->level == '100') {
                            $aqq->whereNotNull('applicant_id');
                        } else {
                            $aqq->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->get();
    }
};
?>

<div class="p-8 bg-white min-h-screen text-zinc-900 font-sans">
    {{-- Branding & Controls --}}
    <div class="flex items-center justify-between border-b-2 border-zinc-200 pb-6 mb-6">
        <div class="flex items-center gap-6">
            @if($institution?->logo_path)
                <img src="{{ $institution->logo_url }}" class="w-16 h-16 object-contain" alt="Logo">
            @else
                <x-app-logo class="w-16 h-16" />
            @endif
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tight text-zinc-800 leading-none mb-1">
                    {{ $institution?->name ?? config('app.institution_name', 'Institution Name') }}
                </h1>
                <div class="text-xs text-zinc-500 font-medium space-y-0.5">
                    <p>{{ $institution?->address ?? config('app.institution_address', 'Address Line') }}</p>
                    <p class="flex items-center gap-3">
                        <span class="flex items-center gap-1">✉️ {{ $institution?->email ?? config('app.institution_email') }}</span>
                        <span class="flex items-center gap-1">📞 {{ $institution?->phone ?? config('app.institution_phone') }}</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-2 print:hidden">
            <flux:button variant="ghost" size="sm" icon="chevron-left" :href="route('cms.invoices.students', ['invoice' => $invoice->id])">Back to List</flux:button>
            <flux:button variant="primary" size="sm" icon="printer" onclick="window.print()">Print Report</flux:button>
        </div>

        <div class="hidden print:block text-right">
            <h2 class="text-xl font-black uppercase text-zinc-300 tracking-widest leading-none mb-1">Financial Report</h2>
            <p class="text-sm font-bold text-zinc-800">{{ $invoice->title }}</p>
            <p class="text-xs text-zinc-500">{{ $invoice->academicSession->name }} Session</p>
        </div>
    </div>

    {{-- Report Info --}}
    <div class="grid grid-cols-2 gap-8 mb-6 text-sm">
        <div>
            <p class="text-zinc-500 uppercase text-[10px] font-bold">Report Parameters</p>
            <div class="mt-1 space-y-0.5">
                <p><span class="text-zinc-400">Level Filter:</span> {{ $level === 'all' ? 'All Levels' : $level . ' Level' }}</p>
                <p><span class="text-zinc-400">Status Filter:</span> {{ ucfirst($status) }}</p>
                @if($search)
                    <p><span class="text-zinc-400">Search:</span> "{{ $search }}"</p>
                @endif
            </div>
        </div>
        <div class="text-right">
            <p class="text-zinc-500 uppercase text-[10px] font-bold">Generated On</p>
            <p class="mt-1">{{ now()->format('d M, Y | h:i A') }}</p>
        </div>
    </div>

    {{-- Summary Cards --}}
    @php 
        $items = $this->studentInvoices();
        $totalInvoiced = $items->sum('total_amount');
        $totalPaid = $items->sum('amount_paid');
        $totalOutstanding = $items->sum('balance');
    @endphp
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="border border-zinc-100 p-3 bg-zinc-50/50 rounded-sm">
            <p class="text-zinc-500 uppercase text-[9px] font-bold">Total Invoiced</p>
            <p class="text-base font-bold">₦{{ number_format($totalInvoiced, 2) }}</p>
        </div>
        <div class="border border-zinc-100 p-3 bg-zinc-50/50 rounded-sm">
            <p class="text-zinc-500 uppercase text-[9px] font-bold">Total Collected</p>
            <p class="text-base font-bold text-green-700">₦{{ number_format($totalPaid, 2) }}</p>
        </div>
        <div class="border border-zinc-100 p-3 bg-zinc-50/50 rounded-sm">
            <p class="text-zinc-500 uppercase text-[9px] font-bold">Total Outstanding</p>
            <p class="text-base font-bold text-orange-700">₦{{ number_format($totalOutstanding, 2) }}</p>
        </div>
    </div>

    {{-- Table --}}
    <table class="w-full text-left text-[10px]">
        <thead class="bg-zinc-100 border-y border-zinc-200 uppercase tracking-tighter">
            <tr>
                <th class="py-2 px-1 font-bold w-6">S/N</th>
                <th class="py-2 px-1 font-bold">Student Name / Matric</th>
                <th class="py-2 px-1 font-bold">Program / Level</th>
                <th class="py-2 px-1 font-bold text-right">Invoiced</th>
                <th class="py-2 px-1 font-bold text-right">Paid</th>
                <th class="py-2 px-1 font-bold text-right">Balance</th>
                <th class="py-2 px-1 font-bold text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200">
            @foreach($items as $index => $item)
                <tr>
                    <td class="py-1 px-1">{{ $index + 1 }}</td>
                    <td class="py-1 px-1">
                        <p class="font-bold">{{ $item->student ? $item->student->full_name : $item->applicant->full_name }}</p>
                        <p class="text-zinc-500 font-mono text-[9px]">{{ $item->student ? $item->student->matric_number : $item->applicant->application_number }}</p>
                    </td>
                    <td class="py-1 px-1 leading-tight">
                        <p>{{ $item->student ? ($item->student->program?->acronym ?? Str::limit($item->student->program?->name, 20)) : ($item->applicant->program?->acronym ?? Str::limit($item->applicant->program?->name, 20)) }}</p>
                        <p class="text-zinc-500">{{ $item->student ? $item->student->currentLevel($item->invoice->academicSession) . 'L' : 'Applicant' }}</p>
                    </td>
                    <td class="py-1 px-1 text-right">₦{{ number_format($item->total_amount, 2) }}</td>
                    <td class="py-1 px-1 text-right">₦{{ number_format($item->amount_paid, 2) }}</td>
                    <td class="py-1 px-1 text-right font-semibold {{ $item->balance > 0 ? 'text-orange-700' : '' }}">
                        ₦{{ number_format($item->balance, 2) }}
                    </td>
                    <td class="py-1 px-1 text-center uppercase font-bold text-[8px]">
                        {{ $item->status }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="bg-zinc-50 font-bold border-t border-zinc-300">
            <tr>
                <td colspan="3" class="py-2 px-2 text-right">TOTALS:</td>
                <td class="py-2 px-2 text-right">₦{{ number_format($totalInvoiced, 2) }}</td>
                <td class="py-2 px-2 text-right">₦{{ number_format($totalPaid, 2) }}</td>
                <td class="py-2 px-2 text-right">₦{{ number_format($totalOutstanding, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Footer --}}
    <div class="mt-8 pt-6 border-t border-zinc-100 flex justify-between items-end">
        <div class="text-[9px] text-zinc-400 italic">
            <p>Generated by: {{ auth()->user()->name }}</p>
            <p>Verification QR code or stamp may be required for official purposes.</p>
        </div>
        <div class="text-center w-48 border-t border-zinc-300 pt-1">
            <p class="text-[10px] font-bold uppercase">Authorized Signatory</p>
        </div>
    </div>

    <style>
        @media print {
            .bg-zinc-50 { background-color: #f4f4f5 !important; }
            .bg-zinc-100 { background-color: #f4f4f5 !important; }
            @page { margin: 1.5cm; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
    
    <script>
        window.onload = function() {
            // Uncomment to auto-print
            // window.print();
        }
    </script>
</div>
