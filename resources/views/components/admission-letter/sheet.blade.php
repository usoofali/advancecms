@props([
    /** @var array<string, mixed> $letter */
    'letter' => [],
    /** When set, used as document.title before print so “Save as PDF” defaults to this name. */
    'printFilename' => null,
])

@php
    $l = $letter;
@endphp

{{-- Screen wrapper (not printed) --}}
<div class="min-h-screen print:min-h-0 print:h-auto bg-zinc-100 py-8 px-4 print:bg-white print:p-0 max-w-full overflow-x-auto print:overflow-visible">

    {{-- A4 sheet --}}
    <div id="letter" class="a4-page mx-auto bg-white text-[#1a1a1a] shadow-xl print:shadow-none
                font-serif text-[10.5pt] leading-snug">

        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>

        <div class="flex items-center gap-4 px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b]">
            @if (!empty($l['institution_logo_path']))
                <img src="{{ asset('storage/'.$l['institution_logo_path']) }}" alt="Logo"
                    class="h-16 w-16 object-contain flex-shrink-0">
            @else
                <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center flex-shrink-0">
                    <span class="text-2xl font-bold text-white">{{ substr($l['institution_name'], 0, 1) }}</span>
                </div>
            @endif
            <div class="flex-1 text-center">
                <h1 class="text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                    {{ $l['institution_name'] }}
                </h1>
                <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                    {{ $l['institution_address'] ?? __('Address Not Set') }}
                </p>
                <p class="text-[8.5pt] text-zinc-500">
                    {{ __('Email') }}: {{ $l['institution_email'] ?? 'info@institution.edu.ng' }}
                    &nbsp;|&nbsp;
                    {{ __('Tel') }}: {{ $l['institution_phone'] ?? 'N/A' }}
                </p>
            </div>
            <div class="flex-shrink-0 text-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($l['qr_data']) }}"
                    alt="{{ __('Verification QR') }}" class="w-16 h-16 border border-zinc-300 bg-white p-0.5">
                <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">{{ __('Scan to verify') }}</p>
            </div>
        </div>

        <div class="text-center py-3 mx-8 border-b border-dashed border-zinc-300">
            <h2
                class="text-[11.5pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
                {{ $l['letter_title'] }}
            </h2>
        </div>

        <div class="flex justify-between items-start px-8 pt-3 pb-2 text-[9pt]">
            <div>
                <span class="font-bold text-zinc-500">{{ __('Ref No') }}:</span>
                <span class="ml-1 font-mono font-semibold text-[#1a3c6b]">{{ $l['ref'] }}</span>
            </div>
            <div>
                <span class="font-bold text-zinc-500">{{ __('Date') }}:</span>
                <span class="ml-1">{{ $l['letter_date_formatted'] }}</span>
            </div>
        </div>

        <div class="px-8 pb-4 space-y-3 text-[10.5pt] leading-relaxed text-justify">

            <p class="font-semibold">{{ __('Dear') }} {{ strtoupper($l['addressee_full_name']) }},</p>

            <p>
                {{ __('I am pleased to inform you that following your application and subsequent review of your credentials, the Management of') }}
                <strong>{{ $l['institution_name'] }}</strong>
                {{ __('has offered you') }}
                <strong>{{ __('Provisional Admission') }}</strong>
                {{ __('for the') }}
                <strong>{{ $l['academic_session_label'] }}</strong>
                {{ __('Academic Session, to pursue a course of study in:') }}
            </p>

            <div class="flex items-center gap-3 bg-[#f0f5ff] border-l-4 border-[#1a3c6b] px-4 py-2 my-1">
                <div class="flex-1">
                    <p class="text-[11pt] font-extrabold text-[#1a3c6b] uppercase">{{ $l['program_name'] }}</p>
                    <p class="text-[8.5pt] text-zinc-500">
                        {!! $l['program_meta_line'] !!}
                    </p>
                </div>
                @if ($l['is_enrolled'] && !empty($l['matric_number']))
                    <div class="text-right flex-shrink-0">
                        <p class="text-[8pt] text-zinc-500 font-bold">{{ __('Matric No.') }}</p>
                        <p class="text-[11pt] font-extrabold text-[#1a3c6b] font-mono">{{ $l['matric_number'] }}</p>
                    </div>
                @endif
            </div>

            @if ($l['is_enrolled'] && !empty($l['matric_number']))
                <p>
                    {{ __('Your designated') }} <strong>{{ __('Matriculation Number') }}</strong>
                    {{ __('is') }} <strong>{{ $l['matric_number'] }}</strong>.
                    {{ __('Please quote this number in all your future correspondence with the institution. You are expected to log in to the student portal and complete your course registration promptly.') }}
                </p>
            @elseif ($l['show_fee_paragraph'])
                <p>
                    {{ __('To formally accept this offer and generate your official') }}
                    <strong>{{ __('Matriculation Number') }}</strong>,
                    {{ __('you are required to pay a minimum of') }} <strong>50%</strong>
                    {{ __('of your total Admission Fees via your Applicant Portal. Your Matriculation Number will be issued upon successful enrollment.') }}
                </p>
            @endif

            <p>
                {{ __('This offer is') }} <strong>{{ __('provisional') }}</strong>
                {{ __('and subject to verification of your submitted academic credentials. Any discrepancy discovered during the screening exercise will automatically invalidate this admission. You are required to present all original certificates for verification during registration.') }}
            </p>

            <p>
                {{ __('Accept our warm congratulations and best wishes as you commence this new academic journey.') }}
            </p>
        </div>

        <div class="mx-8 mb-3 border border-zinc-200 rounded overflow-hidden text-[9pt]">
            <table class="w-full">
                <tbody>
                    <tr class="bg-[#f0f5ff]">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-36">{{ $l['details_name_label'] }}
                        </th>
                        <td class="px-3 py-1.5 font-bold text-[#1a3c6b]">{{ $l['details_name_value'] }}</td>
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-28">{{ __('Application No.') }}
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

        <div class="flex justify-between items-end px-8 pb-5 pt-3">
            <div class="text-[8.5pt] text-zinc-400 italic max-w-xs">
                {{ __('This letter is electronically generated and does not require a physical signature. Scan the QR code to verify authenticity.') }}
            </div>
            <div class="text-center">
                <div class="w-full border-b-2 border-[#1a1a1a] mb-1"></div>
                <p class="text-[9.5pt] font-bold">{{ __('Registrar') }}</p>
                <p class="text-[8.5pt] text-zinc-500">{{ $l['institution_name'] }}</p>
            </div>
        </div>

        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>
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
            {{ __('Print / Save PDF') }}
        </button>
        <a href="{{ $l['back_url'] }}"
            class="px-6 py-2 bg-white text-zinc-700 border border-zinc-300 font-medium rounded-lg hover:bg-zinc-50 transition-colors"
            wire:navigate>
            {{ $l['back_label'] }}
        </a>
    </div>
</div>

<style>
    .a4-page {
        width: 100%;
        max-width: 794px;
        height: auto;
        box-sizing: border-box;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 14mm 10mm;
            orphans: 0;
            widows: 0;
        }

        html,
        body {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
            overflow: visible !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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
