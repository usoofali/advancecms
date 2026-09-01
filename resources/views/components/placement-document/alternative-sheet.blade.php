@props([
    'document',
    'content'
])

@php
$student = $document->placement->student;
$institution = $student->institution;
$printFilename = Str::slug($student->full_name . ' ' . $document->document_number) . '.pdf';
$verificationUrl = route('placements.verify', ['number' => urlencode($document->document_number)]);

$adminSignatureUrl = null;
if ($institution) {
    $adminStaff = \App\Models\Staff::where('institution_id', $institution->id)
        ->where('role_id', 2)
        ->whereNotNull('signature_path')
        ->first();
    if ($adminStaff) {
        $adminSignatureUrl = asset('storage/' . $adminStaff->signature_path);
    }
}
@endphp

<div class="p-4 bg-zinc-50 min-h-screen print:bg-white print:p-0">
    <div class="max-w-3xl mx-auto border-2 border-zinc-200 p-8 rounded-lg shadow-sm bg-white print:border-none print:shadow-none print:p-0 print:max-w-full">
        
        {{-- Header Section --}}
        <div class="flex flex-col items-center mb-6 border-b-2 border-black pb-4">
            @if ($institution && $institution->logo_path)
                <img src="{{ asset('storage/' . $institution->logo_path) }}" alt="{{ $institution->name }} Logo" class="h-20 mb-3 object-contain">
            @else
                <div class="h-16 w-16 mb-2 rounded-full bg-zinc-800 flex items-center justify-center flex-shrink-0">
                    <span class="text-2xl font-bold text-white">{{ $institution ? substr($institution->name, 0, 1) : 'I' }}</span>
                </div> 
            @endif
            
            <h1 class="text-2xl font-bold uppercase text-center text-zinc-900">{{ $institution->name ?? 'Institution Name' }}</h1>
            
            @if($institution?->meta)
                {!! $institution->meta !!}
            @endif
            <p class="text-xs text-zinc-600 uppercase text-center max-w-md mt-1">
                {{ $institution->address ?? __('Address Not Set') }}
            </p>
            
            <p class="text-[10px] text-zinc-600 uppercase font-bold mt-1">
                @if($institution->email) EMAIL: {{ $institution->email }} @endif
                @if($institution->phone) | TEL: {{ $institution->phone }} @endif
            </p>
            
            <div class="mt-6 flex justify-between w-full text-sm font-bold border-t border-zinc-200 pt-3">
                <span class="text-zinc-600 uppercase">Ref No: <span class="text-black font-mono">{{ $document->document_number }}</span></span>
                <span class="text-zinc-600 uppercase">Date Issued: <span class="text-black">{{ $document->generated_at->format('d/m/Y') }}</span></span>
            </div>
        </div>

        {{-- Body Section --}}
        <div class="text-sm leading-relaxed text-justify text-zinc-800 min-h-[300px]">
            {!! $content !!}
        </div>

        {{-- Footer Section --}}
        <div class="mt-12 border-t border-zinc-200 pt-6">
            <div class="flex justify-between items-end">
                <div class="text-center w-40">
                    <div class="w-full border-b border-black mb-1 italic text-xs h-12 flex items-end justify-center pb-1">
                        @if($adminSignatureUrl)
                            <img src="{{ $adminSignatureUrl }}" alt="Authorized Signature" class="max-h-10 max-w-full object-contain">
                        @endif
                    </div>
                    <span class="text-[10px] text-zinc-600 uppercase tracking-tighter font-bold">Authorized Signatory</span>
                    <p class="text-[9px] text-zinc-500 uppercase mt-0.5">{{ $institution->name ?? '' }}</p>
                </div>

                <div class="flex flex-col items-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($verificationUrl) }}"
                        alt="Verification QR Code" class="size-20 border border-zinc-200 p-1 bg-white">
                    <span class="text-[9px] text-zinc-500 uppercase tracking-tighter mt-1 font-bold">Scan to Verify</span>
                </div>

                <div class="text-right w-40">
                    <div class="text-[10px] text-zinc-500 uppercase tracking-tighter font-bold">Generation Timestamp</div>
                    <div class="text-xs font-mono mt-1 text-zinc-700">{{ $document->generated_at->format('Y-m-d H:i:s') }}</div>
                    <div class="text-[9px] text-zinc-400 mt-2 italic">This is an automated system generated document.</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .print\:hidden {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
                background-color: white !important;
                background-image: none !important;
                color: black !important;
            }

            .min-h-screen {
                min-height: 0;
            }

            @page {
                margin: 1cm;
                size: A4 portrait;
            }
        }
    </style>

    <div class="mt-8 print:hidden flex justify-center gap-4">
        <button type="button"
            class="px-5 py-2.5 bg-zinc-900 text-white font-semibold rounded-lg shadow-sm hover:bg-zinc-800 transition-colors flex items-center gap-2 text-sm"
            onclick="(function (t) {
                var p = document.title;
                if (t !== null && t !== '') {
                    document.title = t;
                }
                var r = function () {
                    document.title = p;
                    window.removeEventListener('afterprint', r);
                };
                window.addEventListener('afterprint', r);
                window.print();
                setTimeout(r, 2500);
            })({{ \Illuminate\Support\Js::from($printFilename) }});">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print / Save PDF
        </button>
        
        <button type="button"
            class="px-5 py-2.5 bg-white text-zinc-700 border border-zinc-300 font-medium rounded-lg hover:bg-zinc-50 transition-colors flex items-center gap-2 text-sm"
            onclick="window.close();">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Close Window
        </button>
    </div>
</div>
