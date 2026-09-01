@props([
    'document',
    'content'
])

@php
    $student = $document->placement->student;
    $institution = $student->institution;
    $printFilename = Str::slug($student->full_name . ' ' . $document->document_number) . '.pdf';
    $verificationUrl = route('placements.verify', ['number' => urlencode($document->document_number)]);
@endphp

{{-- Screen wrapper (not printed) --}}
<div class="min-h-screen print:min-h-0 print:h-auto bg-zinc-100 py-8 px-4 print:bg-white print:p-0 max-w-full overflow-x-auto print:overflow-visible">

    {{-- A4 sheet --}}
    <div id="document" class="a4-page mx-auto bg-white text-[#1a1a1a] shadow-xl print:shadow-none font-serif text-[10.5pt] leading-snug">

        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>

        <div class="flex items-center gap-4 px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b]">
            @if ($institution && $institution->logo_path)
                <img src="{{ asset('storage/' . $institution->logo_path) }}" alt="Logo" class="h-16 w-16 object-contain flex-shrink-0">
            @else
                <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center flex-shrink-0">
                    <span class="text-2xl font-bold text-white">{{ $institution ? substr($institution->name, 0, 1) : 'I' }}</span>
                </div> 
            @endif
            <div class="flex-1 text-center">
                <h1 class="text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                    {{ $institution->name ?? 'Institution Name' }}
                </h1>
                <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                    {{ $institution->address ?? __('Address Not Set') }}
                </p>
                <p class="text-[8.5pt] text-zinc-500">
                    {{ __('Email') }}: {{ $institution->email ?? 'info@institution.edu.ng' }}
                    &nbsp;|&nbsp;
                    {{ __('Tel') }}: {{ $institution->phone ?? 'N/A' }}
                </p>
            </div>
            <div class="flex-shrink-0 text-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($verificationUrl) }}"
                    alt="{{ __('Verification QR') }}" class="w-16 h-16 border border-zinc-300 bg-white p-0.5">
                <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
            </div>
        </div>

        <div class="flex justify-between items-start px-8 pt-4 pb-2 text-[9pt]">
            <div>
                <span class="font-bold text-zinc-500">{{ __('Ref No') }}:</span>
                <span class="ml-1 font-mono font-semibold text-[#1a3c6b]">{{ $document->document_number }}</span>
            </div>
            <div>
                <span class="font-bold text-zinc-500">{{ __('Date') }}:</span>
                <span class="ml-1">{{ $document->generated_at->format('jS F, Y') }}</span>
            </div>
        </div>

        <div class="px-8 pb-4 pt-2 space-y-3 text-[10.5pt] leading-relaxed text-justify">
            {!! $content !!}
        </div>

        <div class="flex justify-between items-end px-8 pb-5 pt-8 mt-auto">
            <div class="text-[8.5pt] text-zinc-400 italic max-w-xs">
                {{ __('This letter is electronically generated and does not require a physical signature. Scan the QR code to verify authenticity.') }}
            </div>
            <div class="text-center">
                <div class="w-full border-b-2 border-[#1a1a1a] mb-1"></div>
                <p class="text-[9.5pt] font-bold">{{ __('Registrar') }}</p>
                <p class="text-[8.5pt] text-zinc-500">{{ $institution->name ?? '' }}</p>
            </div>
        </div>

        <div class="h-2 w-full mt-4" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>
    </div>

    <div class="print:hidden w-full max-w-[794px] mx-auto mt-6 flex gap-3 justify-center px-2">
        <button type="button"
            class="px-6 py-2 bg-[#1a3c6b] text-white font-semibold rounded-lg shadow hover:bg-blue-800 transition-colors"
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
            <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            {{ __('Print / Save PDF') }}
        </button>
        <button type="button"
            class="px-6 py-2 bg-white text-zinc-700 border border-zinc-300 font-medium rounded-lg hover:bg-zinc-50 transition-colors"
            onclick="window.close();">
            {{ __('Close Window') }}
        </button>
    </div>
</div>

<style>
    .a4-page {
        width: 100%;
        max-width: 794px;
        height: auto;
        min-height: 1123px; /* A4 height at 96 DPI */
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    @media print {
        *, *::before, *::after {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        @page {
            size: A4 portrait;
            margin: 0; /* Minimal margin to allow edge-to-edge rendering */
        }

        html,
        body {
            background: white !important;
            background-image: none !important;
            color: black !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            overflow: visible !important;
        }

        .min-h-screen {
            min-height: 0 !important;
            height: auto !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            overflow: visible !important;
        }

        .a4-page {
            width: 100%;
            max-width: 100%;
            height: auto !important;
            min-height: 0;
            overflow: visible;
            page-break-inside: avoid;
            page-break-after: avoid;
            box-shadow: none !important;
            margin: 0 auto !important;
            border: none !important;
            box-sizing: border-box;
        }

        .print\:hidden {
            display: none !important;
        }

        .print\:bg-white {
            background: white !important;
        }

        .print\:shadow-none {
            box-shadow: none !important;
        }

        .print\:p-0 {
            padding: 0 !important;
        }
    }
</style>
