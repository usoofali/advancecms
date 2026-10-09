@props([
    /** @var array<string, mixed> $letter */
    'letter' => [],
    /** When set, used as document.title before print so “Save as PDF” defaults to this name. */
    'printFilename' => null,
    /** Whether to render the bottom action buttons (Print/Save PDF & Back). */
    'showActions' => true,
])

@php
$l = $letter;
$logoPos = $l['logo_position'] ?? 'left';
$qrPos = $l['qr_position'] ?? 'header_right';
$showQr = (bool) ($l['show_qr_code'] ?? true);
@endphp

{{-- Screen wrapper (not printed) --}}
<div class="{{ $showActions ? 'min-h-screen py-8 px-4 bg-zinc-100 max-w-full overflow-x-auto' : 'p-0 bg-transparent' }} print:min-h-0 print:h-auto print:bg-white print:p-0 print:overflow-visible">

    {{-- A4 sheet --}}
    <div id="letter" class="a4-page mx-auto bg-white text-[#1a1a1a] shadow-xl print:shadow-none
                font-serif text-[10.5pt] leading-snug">

        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>

        @if ($logoPos === 'center')
            <div class="px-4 sm:px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b] text-center relative print:px-8">
                @if (!empty($l['institution_logo_path']))
                    <img src="{{ asset('storage/' . $l['institution_logo_path']) }}" alt="Logo"
                        class="h-16 w-16 object-contain mx-auto mb-2">
                @else
                    <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center mx-auto mb-2">
                        <span class="text-2xl font-bold text-white">{{ substr($l['institution_name'], 0, 1) }}</span>
                    </div> 
                @endif
                <div>
                    <h1 class="text-[12pt] sm:text-[14pt] print:text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                        {{ $l['institution_name'] }}
                    </h1>
                    @if($l['institution_meta'])
                        {!! $l['institution_meta'] !!}
                    @endif
                    <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                        {{ $l['institution_address'] ?? __('Address Not Set') }}
                    </p>
                    <p class="text-[8.5pt] text-zinc-500">
                        {{ __('Email') }}: {{ $l['institution_email'] ?? 'info@institution.edu.ng' }}
                        &nbsp;|&nbsp;
                        {{ __('Tel') }}: {{ $l['institution_phone'] ?? 'N/A' }}
                    </p>
                </div>

                @if ($showQr && $qrPos === 'header_right')
                    <div class="mt-2 sm:mt-0 sm:absolute right-4 sm:right-8 top-5 text-center flex flex-col items-center sm:block print:absolute print:right-8 print:top-5">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 sm:w-16 sm:h-16 border border-zinc-300 bg-white p-0.5">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                @endif
            </div>
        @elseif ($logoPos === 'right')
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-4 sm:px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b] print:flex-row print:px-8">
                @if ($showQr && $qrPos === 'header_right')
                    <div class="flex-shrink-0 text-center order-3 sm:order-1 print:order-1">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 sm:w-16 sm:h-16 border border-zinc-300 bg-white p-0.5">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                @else
                    <div class="hidden sm:block w-16 print:block"></div>
                @endif

                <div class="flex-1 text-center order-2">
                    <h1 class="text-[12pt] sm:text-[14pt] print:text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                        {{ $l['institution_name'] }}
                    </h1>
                    @if($l['institution_meta'])
                        {!! $l['institution_meta'] !!}
                    @endif
                    <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                        {{ $l['institution_address'] ?? __('Address Not Set') }}
                    </p>
                    <p class="text-[8.5pt] text-zinc-500">
                        {{ __('Email') }}: {{ $l['institution_email'] ?? 'info@institution.edu.ng' }}
                        &nbsp;|&nbsp;
                        {{ __('Tel') }}: {{ $l['institution_phone'] ?? 'N/A' }}
                    </p>
                </div>

                @if (!empty($l['institution_logo_path']))
                    <img src="{{ asset('storage/' . $l['institution_logo_path']) }}" alt="Logo"
                        class="h-16 w-16 object-contain flex-shrink-0 order-1 sm:order-3 print:order-3">
                @else
                    <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center flex-shrink-0 order-1 sm:order-3 print:order-3">
                        <span class="text-2xl font-bold text-white">{{ substr($l['institution_name'], 0, 1) }}</span>
                    </div> 
                @endif
            </div>
        @else
            {{-- Default: Logo on left --}}
            <div class="flex flex-col sm:flex-row items-center gap-4 px-4 sm:px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b] print:flex-row print:px-8">
                @if (!empty($l['institution_logo_path']))
                    <img src="{{ asset('storage/' . $l['institution_logo_path']) }}" alt="Logo"
                        class="h-16 w-16 object-contain flex-shrink-0">
                @else
                    <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center flex-shrink-0">
                        <span class="text-2xl font-bold text-white">{{ substr($l['institution_name'], 0, 1) }}</span>
                    </div> 
                @endif

                <div class="flex-1 text-center">
                    <h1 class="text-[12pt] sm:text-[14pt] print:text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                        {{ $l['institution_name'] }}
                    </h1>
                    @if($l['institution_meta'])
                        {!! $l['institution_meta'] !!}
                    @endif
                    <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                        {{ $l['institution_address'] ?? __('Address Not Set') }}
                    </p>
                    <p class="text-[8.5pt] text-zinc-500">
                        {{ __('Email') }}: {{ $l['institution_email'] ?? 'info@institution.edu.ng' }}
                        &nbsp;|&nbsp;
                        {{ __('Tel') }}: {{ $l['institution_phone'] ?? 'N/A' }}
                    </p>
                </div>

                @if ($showQr && $qrPos === 'header_right')
                    <div class="flex-shrink-0 text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 sm:w-16 sm:h-16 border border-zinc-300 bg-white p-0.5">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                @else
                    <div class="hidden sm:block w-16 print:block"></div>
                @endif
            </div>
        @endif

        <div class="text-center py-3 mx-4 sm:mx-8 border-b border-dashed border-zinc-300 print:mx-8">
            <h2
                class="text-[11.5pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
                {{ $l['letter_title'] }}
            </h2>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center px-4 sm:px-8 pt-3 pb-2 text-[9pt] gap-1 sm:gap-0 print:flex-row print:px-8">
            <div>
                <span class="font-bold text-zinc-500">{{ __('Ref No') }}:</span>
                <span class="ml-1 font-mono font-semibold text-[#1a3c6b]">{{ $l['ref'] }}</span>
            </div>
            <div>
                <span class="font-bold text-zinc-500">{{ __('Date') }}:</span>
                <span class="ml-1">{{ $l['letter_date_formatted'] }}</span>
            </div>
        </div>

        <div class="px-4 sm:px-8 pb-4 space-y-3 text-[10pt] sm:text-[10.5pt] leading-relaxed text-left sm:text-justify print:text-[10.5pt] print:text-justify print:px-8">

            @if (!empty($l['salutation']))
                <p class="font-semibold">{!! nl2br(e($l['salutation'])) !!}</p>
            @endif

            @if (!empty($l['opening_text']))
                <p>
                    {!! nl2br(e($l['opening_text'])) !!}
                </p>
            @endif

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-3 bg-[#f0f5ff] border-l-4 border-[#1a3c6b] px-4 py-2 my-1 print:flex-row">
                <div class="flex-1">
                    <p class="text-[10pt] sm:text-[11pt] print:text-[11pt] font-extrabold text-[#1a3c6b] uppercase">{{ $l['program_name'] }}</p>
                    <p class="text-[8.5pt] text-zinc-500">
                        {!! $l['program_meta_line'] !!}
                    </p>
                </div>
                @if ($l['is_enrolled'] && !empty($l['matric_number']))
                    <div class="text-left sm:text-right flex-shrink-0 print:text-right">
                        <p class="text-[8pt] text-zinc-500 font-bold">{{ __('Matric No.') }}</p>
                        <p class="text-[10pt] sm:text-[11pt] print:text-[11pt] font-extrabold text-[#1a3c6b] font-mono">{{ $l['matric_number'] }}</p>
                    </div>
                @endif
            </div>

            @if (!empty($l['body_text']))
                <p>
                    {!! nl2br(e($l['body_text'])) !!}
                </p>
            @endif

            @if (!empty($l['conditions_text']))
                <p>
                    {!! nl2br(e($l['conditions_text'])) !!}
                </p>
            @endif

            @if (!empty($l['closing_text']))
                <p>
                    {!! nl2br(e($l['closing_text'])) !!}
                </p>
            @endif
        </div>

        <div class="mx-4 sm:mx-8 mb-3 border border-zinc-200 rounded overflow-x-auto text-[9pt] print:mx-8 print:overflow-visible">
            <table class="w-full min-w-[480px] sm:min-w-0 print:min-w-full">
                <tbody>
                    <tr class="bg-[#f0f5ff]">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-32 sm:w-36 print:w-36">{{ $l['details_name_label'] }}
                        </th>
                        <td class="px-3 py-1.5 font-bold text-[#1a3c6b]">{{ $l['details_name_value'] }}</td>
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-24 sm:w-28 print:w-28">{{ __('Application No.') }}
                        </th>
                        <td class="px-3 py-1.5 font-mono font-bold">{{ $l['application_number'] ?? '—' }}</td>
                    </tr>
                    <tr class="bg-white">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">{{ __('Email') }}</th>
                        <td class="px-3 py-1.5">{{ $l['email'] ?? '—' }}</td>
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">{{ __('Phone') }}</th>
                        <td class="px-3 py-1.5">{{ $l['phone'] ?? '—' }}</td>
                    </tr>
                    <tr class="bg-[#f0f5ff]">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">{{ __('Session') }}</th>
                        <td class="px-3 py-1.5" colspan="3">{{ $l['session_row_value'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-center sm:items-end gap-4 sm:gap-0 px-4 sm:px-8 pb-5 pt-3 print:flex-row print:items-end print:px-8 print:gap-0">
            <div class="w-full sm:w-[220px] print:w-[220px] flex-shrink-0 flex items-center sm:items-end gap-3 print:items-end">
                @if ($showQr && $qrPos === 'footer_left')
                    <div class="flex-shrink-0 text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 border border-zinc-300 bg-white p-0.5">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                @endif
                <div class="text-[8pt] text-zinc-400 italic leading-snug break-words">
                    {{ __('This letter is electronically generated and does not require a physical signature. Scan the QR code to verify authenticity.') }}
                </div>
            </div>

            @if ($showQr && $qrPos === 'footer_center')
                <div class="flex-1 flex justify-center flex-shrink-0">
                    <div class="text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 border border-zinc-300 bg-white p-0.5 inline-block">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                </div>
            @endif

            <div class="w-full sm:w-[220px] print:w-[220px] flex-shrink-0 flex justify-center sm:justify-end items-end gap-3 print:justify-end">
                @if ($showQr && $qrPos === 'footer_right')
                    <div class="flex-shrink-0 text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data={{ urlencode($l['qr_data']) }}"
                            alt="{{ __('Verification QR') }}" class="w-14 h-14 border border-zinc-300 bg-white p-0.5">
                        <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
                    </div>
                @endif
                <div class="text-center min-w-36">
                    <div class="w-full border-b-2 border-[#1a1a1a] mb-1"></div>
                    <p class="text-[9.5pt] font-bold">{{ $l['signatory_title'] ?? __('Registrar') }}</p>
                    <p class="text-[8.5pt] text-zinc-500">{{ $l['signatory_subtitle'] ?? $l['institution_name'] }}</p>
                </div>
            </div>
        </div>

        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>
    </div>

    @if ($showActions)
        <div class="print:hidden w-full max-w-[794px] mx-auto mt-6 flex flex-col sm:flex-row gap-3 justify-center items-stretch sm:items-center px-2">
            <button type="button"
                class="px-6 py-2 bg-[#1a3c6b] text-white font-semibold rounded-lg shadow hover:bg-blue-800 transition-colors w-full sm:w-auto text-center"
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
                {{ __('Print / Save PDF') }}
            </button>
            <a href="{{ $l['back_url'] }}"
                class="px-6 py-2 bg-white text-zinc-700 border border-zinc-300 font-medium rounded-lg hover:bg-zinc-50 transition-colors w-full sm:w-auto text-center"
                wire:navigate>
                {{ $l['back_label'] }}
            </a>
        </div>
    @endif
</div>

<style>
    .a4-page {
        width: 100%;
        max-width: 794px;
        height: auto;
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
            margin: 12mm 10mm 14mm 10mm;
            orphans: 0;
            widows: 0;
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
