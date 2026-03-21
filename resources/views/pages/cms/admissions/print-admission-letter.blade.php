<?php

use App\Models\Applicant;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Letter')] #[Layout('layouts.guest')] class extends Component
{
    public Applicant $applicant;
    public ?Student $student;

    public function mount(Applicant $applicant): void
    {
        if ($applicant->admission_status !== 'admitted') {
            abort(403, 'Applicant has not been admitted.');
        }

        $this->applicant = $applicant->load(['institution', 'program', 'applicationForm.academicSession']);
        // Find the enrolled student record (may be null if not yet enrolled)
        $this->student = Student::where('email', $applicant->email)->first();
    }
}; ?>

@php
$isOffer = $applicant->enrolled_at && $student;
$letterTitle = $isOffer ? 'OFFER OF PROVISIONAL ADMISSION' : 'NOTIFICATION OF PROVISIONAL ADMISSION';
$ref = $isOffer ? $student->matric_number : 'PENDING/ENROLL/'.$applicant->application_number;
$qrData = implode("\n", array_filter([
$letterTitle,
'Ref: '.$ref,
'Applicant: '.$applicant->full_name,
'Program: '.$applicant->program->name,
'Session: '.$applicant->applicationForm?->academicSession?->name,
'Institution: '.$applicant->institution->name,
'Date: '.$applicant->updated_at->format('d/m/Y'),
]));
@endphp

{{-- Screen wrapper (not printed) --}}
<div class="min-h-screen print:min-h-0 print:h-auto bg-zinc-100 py-8 px-4 print:bg-white print:p-0">

    {{-- A4 sheet --}}
    <div id="letter" class="a4-page mx-auto bg-white text-[#1a1a1a] shadow-xl print:shadow-none
                font-serif text-[10.5pt] leading-snug">

        {{-- ── TOP STRIPE ── --}}
        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>

        {{-- ── HEADER ── --}}
        <div class="flex items-center gap-4 px-8 pt-5 pb-4 border-b-2 border-[#1a3c6b]">
            @if($applicant->institution->logo_path)
            <img src="{{ asset('storage/'.$applicant->institution->logo_path) }}" alt="Logo"
                class="h-16 w-16 object-contain flex-shrink-0">
            @else
            <div class="h-16 w-16 rounded-full bg-[#1a3c6b] flex items-center justify-center flex-shrink-0">
                <span class="text-2xl font-bold text-white">{{ substr($applicant->institution->name,0,1) }}</span>
            </div>
            @endif
            <div class="flex-1 text-center">
                <h1 class="text-[14pt] font-extrabold uppercase tracking-widest text-[#1a3c6b]">
                    {{ $applicant->institution->name }}
                </h1>
                <p class="text-[8.5pt] text-zinc-500 mt-0.5">
                    {{ $applicant->institution->address ?? 'Address Not Set' }}
                </p>
                <p class="text-[8.5pt] text-zinc-500">
                    Email: {{ $applicant->institution->email ?? 'info@institution.edu.ng' }}
                    &nbsp;|&nbsp;
                    Tel: {{ $applicant->institution->phone ?? 'N/A' }}
                </p>
            </div>
            {{-- QR Code (top-right corner) --}}
            <div class="flex-shrink-0 text-center">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($qrData) }}"
                    alt="Verification QR" class="w-16 h-16 border border-zinc-300 bg-white p-0.5">
                <p class="text-[6.5pt] text-zinc-400 mt-0.5 leading-none">Scan to verify</p>
            </div>
        </div>

        {{-- ── LETTER TITLE ── --}}
        <div class="text-center py-3 mx-8 border-b border-dashed border-zinc-300">
            <h2
                class="text-[11.5pt] font-extrabold uppercase tracking-widest text-[#1a3c6b] underline underline-offset-2">
                {{ $letterTitle }}
            </h2>
        </div>

        {{-- ── META ROW ── --}}
        <div class="flex justify-between items-start px-8 pt-3 pb-2 text-[9pt]">
            <div>
                <span class="font-bold text-zinc-500">Ref No:</span>
                <span class="ml-1 font-mono font-semibold text-[#1a3c6b]">{{ $ref }}</span>
            </div>
            <div>
                <span class="font-bold text-zinc-500">Date:</span>
                <span class="ml-1">{{ $applicant->updated_at->format('jS F, Y') }}</span>
            </div>
        </div>

        {{-- ── BODY ── --}}
        <div class="px-8 pb-4 space-y-3 text-[10.5pt] leading-relaxed text-justify">

            <p class="font-semibold">Dear {{ strtoupper($applicant->full_name) }},</p>

            <p>
                I am pleased to inform you that following your application and subsequent review of your credentials,
                the Management of <strong>{{ $applicant->institution->name }}</strong> has offered you
                <strong>Provisional Admission</strong> for the
                <strong>{{ $applicant->applicationForm?->academicSession?->name }}</strong> Academic Session,
                to pursue a course of study in:
            </p>

            {{-- Program highlight --}}
            <div class="flex items-center gap-3 bg-[#f0f5ff] border-l-4 border-[#1a3c6b] px-4 py-2 my-1">
                <div class="flex-1">
                    <p class="text-[11pt] font-extrabold text-[#1a3c6b] uppercase">{{ $applicant->program->name }}</p>
                    <p class="text-[8.5pt] text-zinc-500">
                        Entry Level: 100L
                        &nbsp;|&nbsp;
                        Mode: Full-time
                        &nbsp;|&nbsp;
                        Award: Certificate
                    </p>
                </div>
                @if($isOffer)
                <div class="text-right flex-shrink-0">
                    <p class="text-[8pt] text-zinc-500 font-bold">Matric No.</p>
                    <p class="text-[11pt] font-extrabold text-[#1a3c6b] font-mono">{{ $student->matric_number }}</p>
                </div>
                @endif
            </div>

            @if($isOffer)
            <p>
                Your designated <strong>Matriculation Number</strong> is <strong>{{ $student->matric_number }}</strong>.
                Please quote this number in all your future correspondence with the institution.
                You are expected to log in to the student portal and complete your course registration promptly.
            </p>
            @else
            <p>
                To formally accept this offer and generate your official <strong>Matriculation Number</strong>,
                you are required to pay a minimum of <strong>50%</strong> of your total Admission Fees via your
                Applicant Portal. Your Matriculation Number will be issued upon successful enrollment.
            </p>
            @endif

            <p>
                This offer is <strong>provisional</strong> and subject to verification of your submitted academic
                credentials. Any discrepancy discovered during the screening exercise will automatically invalidate
                this admission. You are required to present all original certificates for verification during
                registration.
            </p>

            <p>
                Accept our warm congratulations and best wishes as you commence this new academic journey.
            </p>
        </div>

        {{-- ── APPLICANT DETAILS TABLE ── --}}
        <div class="mx-8 mb-3 border border-zinc-200 rounded overflow-hidden text-[9pt]">
            <table class="w-full">
                <tbody>
                    <tr class="bg-[#f0f5ff]">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-36">Applicant Name</th>
                        <td class="px-3 py-1.5 font-bold text-[#1a3c6b]">{{ strtoupper($applicant->full_name) }}</td>
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600 w-28">Application No.</th>
                        <td class="px-3 py-1.5 font-mono font-bold">{{ $applicant->application_number }}</td>
                    </tr>
                    <tr class="bg-white">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">Email</th>
                        <td class="px-3 py-1.5">{{ $applicant->email }}</td>
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">Phone</th>
                        <td class="px-3 py-1.5">{{ $applicant->phone ?? '—' }}</td>
                    </tr>
                    <tr class="bg-[#f0f5ff]">
                        <th class="px-3 py-1.5 text-left font-semibold text-zinc-600">Session</th>
                        <td class="px-3 py-1.5" colspan="3">{{ $applicant->applicationForm?->academicSession?->name }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ── SIGNATURE ── --}}
        <div class="flex justify-between items-end px-8 pb-5 pt-3">
            <div class="text-[8.5pt] text-zinc-400 italic max-w-xs">
                This letter is electronically generated and does not require a physical signature.
                Scan the QR code to verify authenticity.
            </div>
            <div class="text-center">
                <div class="w-full border-b-2 border-[#1a1a1a] mb-1"></div>
                <p class="text-[9.5pt] font-bold">Registrar</p>
                <p class="text-[8.5pt] text-zinc-500">{{ $applicant->institution->name }}</p>
            </div>
        </div>

        {{-- ── FOOTER STRIPE ── --}}
        <div class="h-2 w-full" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>
    </div>

    {{-- ── PRINT BUTTON (screen only) ── --}}
    <div class="print:hidden max-w-[794px] mx-auto mt-6 flex gap-3 justify-center">
        <button onclick="window.print()"
            class="px-6 py-2 bg-[#1a3c6b] text-white font-semibold rounded-lg shadow hover:bg-blue-800 transition-colors">
            🖨 Print / Save PDF
        </button>
        @php
        $backUrl = auth()->check() && auth()->user()->can('view_applications')
        ? route('cms.admissions.show', $applicant)
        : route('applicant.portal', ['application_number' => $applicant->application_number]);
        @endphp
        <a href="{{ $backUrl }}"
            class="px-6 py-2 bg-white text-zinc-700 border border-zinc-300 font-medium rounded-lg hover:bg-zinc-50 transition-colors"
            wire:navigate>
            ← Back to Portal
        </a>
    </div>
</div>

<style>
    /* A4 dimensions: 210mm × 297mm ≈ 794px × 1123px at 96dpi */
    .a4-page {
        width: 794px;
        height: auto;
        box-sizing: border-box;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 0;
            orphans: 0;
            widows: 0;
        }

        html,
        body {
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            height: 297mm !important;
            overflow: hidden !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .min-h-screen {
            min-height: 0 !important;
            height: 297mm !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .a4-page {
            width: 210mm;
            height: 295mm; /* Reduced slightly more to ensure perfect fit */
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid; /* Changed from always to avoid to prevent trailing blank page */
            box-shadow: none !important;
            margin: 0 auto !important;
            border: none !important;
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