<?php

use App\Models\Student;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\CourseRegistration;
use App\Models\RegistrationStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Examination Card')] class extends Component {
    public ?Student $student = null;
    public int|string $session_id = '';
    public int|string $semester_id = '';

    public function mount(): void
    {
        $user = auth()->user();
        
        // Students can only see their own
        if ($user->hasRole('Student')) {
            $this->student = Student::where('email', $user->email)->with('program.department.institution')->first();
        } else {
            // Staff can view a specific student via query param
            if (request()->has('student')) {
                $this->student = Student::with('program.department.institution')->find(request('student'));
            }
        }

        if (request()->has('session')) {
            $this->session_id = request('session');
        } else {
            $activeSession = AcademicSession::where('status', 'active')->first();
            if ($activeSession) {
                $this->session_id = $activeSession->id;
            }
        }

        if (request()->has('semester')) {
            $this->semester_id = request('semester');
        }
    }

    public function with(App\Services\PaymentAccessService $accessService, App\Services\StudentInvoiceService $invoiceService): array
    {
        $registrations = collect();
        $carryovers = collect();
        $regStatus = null;
        $session = null;
        $semester = null;
        $canAccess = true;
        $missingInvoice = null;

        if ($this->student && $this->session_id && $this->semester_id) {
            $session = AcademicSession::find($this->session_id);
            $semester = Semester::find($this->semester_id);
            
            // Check Payment Access
            $missingTemplates = $accessService->getMissingInvoicesForExamCard($this->student, $session, $semester);
            $canAccess = $missingTemplates->isEmpty();

            if ($canAccess) {
                $allRegistrations = CourseRegistration::with('course')
                    ->where('student_id', $this->student->id)
                    ->where('academic_session_id', $this->session_id)
                    ->where('semester_id', $this->semester_id)
                    ->get();

                $registrations = $allRegistrations->where('is_carryover', false)->values();
                $carryovers = $allRegistrations->where('is_carryover', true)->values();

                $regStatus = RegistrationStatus::where('student_id', $this->student->id)
                    ->where('academic_session_id', $this->session_id)
                    ->where('semester_id', $this->semester_id)
                    ->first();
            } else {
                $missingInvoices = collect();
                // Find what invoice is blocking so the UI can show its details
                foreach ($missingTemplates as $template) {
                    $inv = $invoiceService->materializeInvoice($this->student, $template);
                    if ($inv) {
                        $missingInvoices->push($inv);
                    }
                }
            }
        }

        return [
            'sessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'registrations' => $registrations,
            'carryovers' => $carryovers,
            'regStatus' => $regStatus,
            'session' => $session,
            'semester' => $semester,
            'canAccess' => $canAccess,
            'missingInvoice' => $missingInvoice,
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-6">
    {{-- Filter UI (Hidden in Print) --}}
    <flux:card class="print:hidden space-y-6">
        <flux:heading size="lg">{{ __('Examination Card Generation') }}</flux:heading>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:select wire:model.live="session_id" :label="__('Academic Session')">
                <option value="null">{{ __('Select Session') }}</option>
                @foreach ($sessions as $session)
                <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" :disabled="!$session_id">
                <option value="null">{{ __('Select Semester') }}</option>
                @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}">{{ ucfirst($semester->name) }}</option>
                @endforeach
            </flux:select>
        </div>

        @if ($student && $session_id && $semester_id)
        <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button icon="printer" variant="primary" onclick="window.print()">
                {{ __('Print Examination Card') }}
            </flux:button>
        </div>
        @endif
    </flux:card>

    @if ($student && $session_id && $semester_id)
    @if ($canAccess)
    {{-- Printable Card --}}
    <div
        class="bg-white dark:bg-zinc-900 border-2 border-zinc-300 dark:border-zinc-700 p-4 sm:p-6 shadow-sm rounded-xl print:shadow-none print:m-0 print:border-2 print:border-black print:rounded-none">

        {{-- Card Header & Branding --}}
        <div class="flex items-center justify-between border-b-2 border-black pb-2 mb-4">
            @php $inst = $student->program->department->institution; @endphp
            <div class="flex gap-4 items-center">
                @if ($inst->logo_path)
                <img src="{{ asset('storage/'.$inst->logo_path) }}" class="h-16 w-16 object-contain"
                    alt="Institution Logo">
                @else
                <div class="h-16 w-16 bg-zinc-100 border border-zinc-300 flex items-center justify-center">
                    <span class="text-[10px] uppercase font-bold text-zinc-400">LOGO</span>
                </div>
                @endif
                <div>
                    <h1
                        class="text-lg md:text-xl font-black uppercase tracking-tight text-zinc-900 dark:text-white leading-tight">
                        {{ $inst->name }}
                    </h1>
                    <p
                        class="text-[10px] md:text-xs font-bold uppercase text-zinc-600 dark:text-zinc-400 tracking-widest mt-1">
                        {{ $inst->address ?? 'Official Examination Card' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center">
                @php
                $qrData = "Exam Card\n"
                . "Student: {$student->matric_number}\n"
                . "Session: " . ($session?->name ?? 'N/A') . "\n"
                . "Semester: " . ($semester?->name ?? 'N/A') . "\n"
                . "Generated: " . now()->format('Y-m-d H:i');
                @endphp
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($qrData) }}"
                    alt="Verification QR Code" class="size-16 border border-zinc-300 p-0.5 bg-white">
                <span class="text-[7px] font-bold uppercase tracking-wide mt-1">Scan Verify</span>
            </div>
        </div>

        <div class="text-center mb-4">
            <h2
                class="text-lg font-bold uppercase tracking-widest bg-zinc-900 text-white dark:bg-white dark:text-black inline-block px-3 py-1 rounded-sm">
                {{ __('Student Examination Card') }}
            </h2>
            <div class="font-bold uppercase tracking-widest text-[10px] mt-2">
                {{ $session?->name }} Academic Session &bull; {{ $semester?->name }} Semester
            </div>
        </div>

        {{-- Student Details --}}
        <div class="grid grid-cols-4 gap-4 mb-4 border-2 border-zinc-200 p-2 sm:p-3 rounded-sm">
            <div class="col-span-3 space-y-1">
                <div class="flex border-b border-dashed border-zinc-300 pb-0.5">
                    <span class="w-28 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                        __('Full Name:') }}</span>
                    <span class="font-bold uppercase text-xs w-full font-serif">{{ $student->full_name }}</span>
                </div>
                <div class="flex border-b border-dashed border-zinc-300 pb-0.5">
                    <span class="w-28 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                        __('Matric No:') }}</span>
                    <span class="font-bold font-mono text-xs w-full">{{ $student->matric_number }}</span>
                </div>
                <div class="flex border-b border-dashed border-zinc-300 pb-0.5">
                    <span class="w-28 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                        __('Department:') }}</span>
                    <span class="font-bold uppercase text-[10px] w-full">{{ $student->program->department->name
                        }}</span>
                </div>
                <div class="flex border-b border-dashed border-zinc-300 pb-0.5">
                    <span class="w-28 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                        __('Program:') }}</span>
                    <span class="font-bold uppercase text-[10px] w-full">{{ $student->program->name }}</span>
                </div>
                <div class="flex pb-0.5 gap-4">
                    <div class="flex flex-1 border-b border-dashed border-zinc-300">
                        <span
                            class="w-16 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                            __('Level:') }}</span>
                        <span class="font-bold uppercase text-[10px] w-full">{{ $student->currentLevel($session)
                            }}</span>
                    </div>
                    <div class="flex flex-1 border-b border-dashed border-zinc-300">
                        <span
                            class="w-16 font-bold uppercase text-[9px] tracking-wide text-zinc-500 whitespace-nowrap">{{
                            __('Gender:') }}</span>
                        <span class="font-bold uppercase text-[10px] w-full">{{ $student->gender }}</span>
                    </div>
                </div>
            </div>

            <div class="col-span-1 flex justify-end items-start border-l-2 border-zinc-200 pl-3">
                <div
                    class="w-20 h-24 sm:w-24 sm:h-28 border-[3px] border-zinc-800 bg-zinc-50 flex items-center justify-center overflow-hidden">
                    @if ($student->photo_path)
                    <img src="{{ $student->photo_url }}" class="w-full h-full object-cover">
                    @else
                    <span
                        class="text-[8px] text-zinc-400 font-bold uppercase text-center align-middle">Passport<br>Photograph</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Courses Table --}}
        <h3 class="font-black uppercase tracking-widest text-xs mb-1 border-b border-black inline-block pb-0.5">{{
            __('Approved Courses For Examination') }}</h3>

        <table class="w-full text-[10px] border-collapse border-2 border-zinc-800 mb-4">
            <thead>
                <tr class="bg-zinc-200 dark:bg-zinc-800 print:bg-zinc-200">
                    <th
                        class="py-1 px-1 border border-zinc-800 uppercase font-bold text-[8px] tracking-wide w-8 text-center text-zinc-600 print:text-black">
                        S/N</th>
                    <th
                        class="py-1 px-1 border border-zinc-800 uppercase font-black text-[9px] tracking-wide w-20 text-zinc-800 print:text-black">
                        Course Code</th>
                    <th
                        class="py-1 px-2 border border-zinc-800 uppercase font-black text-[9px] tracking-wide text-left text-zinc-800 print:text-black">
                        Course Title</th>
                    <th
                        class="py-1 px-1 border border-zinc-800 uppercase font-black text-[8px] tracking-wide w-12 text-center text-zinc-800 print:text-black">
                        Units</th>
                    <th
                        class="py-1 px-2 border border-zinc-800 uppercase font-black text-[8px] tracking-wide w-24 text-center text-zinc-600 print:text-black">
                        Invigilator Sign</th>
                </tr>
            </thead>
            <tbody>
                @php $counter = 1; @endphp
                {{-- Normal Registrations --}}
                @foreach ($registrations as $reg)
                <tr>
                    <td
                        class="py-1 px-1 border border-zinc-800 text-center font-semibold text-zinc-600 print:text-black">
                        {{ $counter++ }}</td>
                    <td class="py-1 px-1 border border-zinc-800 font-mono font-bold">{{ $reg->course->course_code }}
                    </td>
                    <td
                        class="py-1 px-2 border border-zinc-800 font-serif font-bold text-[10px] truncate max-w-[200px]">
                        {{ $reg->course->title }}</td>
                    <td class="py-1 px-1 border border-zinc-800 text-center font-bold">{{ $reg->course->credit_unit }}
                    </td>
                    <td class="py-1 px-2 border border-zinc-800 text-center"></td>
                </tr>
                @endforeach

                {{-- Carryovers Section --}}
                @if($carryovers->isNotEmpty())
                <tr class="bg-zinc-100 print:bg-zinc-100">
                    <td colspan="5"
                        class="py-0.5 px-2 border border-zinc-800 font-black uppercase tracking-wide text-[8px] italic text-zinc-600 print:text-black">
                        &#9660; Carryover Courses &#9660;
                    </td>
                </tr>
                @foreach ($carryovers as $reg)
                <tr>
                    <td
                        class="py-1 px-1 border border-zinc-800 text-center font-semibold text-red-700 print:text-black">
                        {{ $counter++ }}</td>
                    <td class="py-1 px-1 border border-zinc-800 font-mono font-bold text-red-700 print:text-black">{{
                        $reg->course->course_code }}</td>
                    <td
                        class="py-1 px-2 border border-zinc-800 font-serif font-bold text-[10px] text-red-700 print:text-black truncate max-w-[200px]">
                        {{ $reg->course->title }} *CO*</td>
                    <td class="py-1 px-1 border border-zinc-800 text-center font-bold text-red-700 print:text-black">{{
                        $reg->course->credit_unit }}</td>
                    <td class="py-1 px-2 border border-zinc-800 text-center"></td>
                </tr>
                @endforeach
                @endif

                @if($registrations->isEmpty() && $carryovers->isEmpty())
                <tr>
                    <td colspan="5" class="py-6 px-2 border border-zinc-800 text-center font-bold italic text-zinc-500">
                        No courses registered for the selected session and semester.
                    </td>
                </tr>
                @endif
            </tbody>
            @if($registrations->isNotEmpty() || $carryovers->isNotEmpty())
            <tfoot>
                <tr class="bg-zinc-50 print:bg-zinc-50">
                    <td colspan="3"
                        class="py-1 px-3 border border-zinc-800 text-right font-black uppercase tracking-widest text-[9px] text-zinc-700 print:text-black">
                        Total Units:</td>
                    <td class="py-1 px-1 border border-zinc-800 text-center font-black text-xs">
                        {{ $registrations->sum(fn($r) => $r->course->credit_unit) + $carryovers->sum(fn($r) =>
                        $r->course->credit_unit) }}
                    </td>
                    <td class="px-1 border border-zinc-800"></td>
                </tr>
            </tfoot>
            @endif
        </table>

        {{-- Verification Signatures --}}
        <div class="mt-4 pt-2">
            <h4
                class="font-bold uppercase text-[9px] tracking-widest mb-4 underline decoration-dashed underline-offset-4">
                Clearance & Approvals</h4>
            <div class="grid grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="border-b border-black h-8 mb-1.5"></div>
                    <span
                        class="font-bold uppercase text-[8px] tracking-widest block text-zinc-600 print:text-black">Student's
                        Signature & Date</span>
                </div>
                <div class="text-center">
                    <div class="border-b border-black h-8 mb-1.5"></div>
                    <span
                        class="font-bold uppercase text-[8px] tracking-widest block text-zinc-600 print:text-black">Bursary
                        Clearance (Stamp/Sign)</span>
                </div>
                <div class="text-center">
                    <div class="border-b border-black h-8 mb-1.5 relative">
                        {{-- Watermark stamp indicator --}}
                        <div
                            class="absolute inset-0 flex items-center justify-center opacity-10 font-bold uppercase outline-2 outline -rotate-12 text-[10px] text-zinc-500">
                            HOD OFFICIAL STAMP
                        </div>
                    </div>
                    <span
                        class="font-bold uppercase text-[8px] tracking-widest block text-zinc-600 print:text-black">Level
                        Coordinator / HOD Sign & Date</span>
                </div>
            </div>
        </div>

        {{-- Exam Rules Instructions --}}
        <div class="mt-4 border-[1.5px] border-zinc-300 py-2 bg-zinc-50 print:bg-transparent px-3 rounded-sm">
            <h5
                class="font-black uppercase text-[8px] tracking-widest mb-0.5 mt-0.5 underline text-zinc-800 print:text-black">
                Important Examination Instructions:</h5>
            <ol
                class="list-decimal pl-4 text-[7px] font-medium leading-tight font-serif space-y-0.5 text-zinc-700 print:text-black">
                <li>Students must present this Official Examination Card to the invigilator at every examination.</li>
                <li>The card MUST be duly signed and stamped by the designated signatories; otherwise, it is invalid.
                </li>
                <li>No student will be allowed into the examination hall without a valid Student ID Card and this
                    Examination Card.</li>
                <li>Mutilation or unauthorized alteration of this card is an examination offense and will be strictly
                    penalized.</li>
                <li>Students must be seated in the examination hall at least 30 minutes before the commencement of the
                    exam.</li>
            </ol>
        </div>

    </div>
    @else
    {{-- Payment Required UI --}}
    <flux:card class="border-red-200 bg-red-50/30 dark:bg-red-950/10 dark:border-red-900/50">
        <div class="text-center py-10 px-6">
            <div
                class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                <flux:icon.credit-card class="size-8 text-red-600 dark:text-red-400" />
            </div>

            <flux:heading size="xl" class="mb-2">{{ __('Examination Fee Payment Required') }}</flux:heading>
            <flux:subheading class="max-w-md mx-auto mb-8">
                {{ __('To access and print your examination card for the selected semester, you must first complete the
                required payment.') }}
            </flux:subheading>

            <div class="flex flex-col items-center gap-4">
                @if (isset($missingInvoices) && $missingInvoices->isNotEmpty())
                <div class="space-y-4 w-full max-w-sm mx-auto text-left">
                    @foreach ($missingInvoices as $inv)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $inv->invoice->title }}</div>
                            <div class="text-xs text-zinc-500 font-mono tracking-wider mt-1">₦{{ number_format($inv->balance, 2) }}</div>
                        </div>
                        <flux:button href="{{ route('cms.students.portal-invoices', $inv->id) }}" variant="primary" size="sm" icon="credit-card" class="shrink-0">
                            {{ __('Pay') }}
                        </flux:button>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <p class="mt-8 text-xs text-red-600 dark:text-red-400 font-medium">
                {{ __('Note: Payments are processed instantly. Your card will be unlocked immediately after successful
                transaction.') }}
            </p>
        </div>
    </flux:card>
    @endif
    @else
    <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
        <flux:icon.document-magnifying-glass class="size-12 mx-auto mb-4 text-zinc-300" />
        <h3 class="font-medium text-zinc-900 dark:text-white mb-1">{{ __('No Data Available') }}</h3>
        <p class="text-sm">{{ __('Please select a session and semester to generate your examination card.') }}</p>
    </div>
    @endif

    <style>
        @media print {
            body {
                font-family: Arial, Helvetica, sans-serif;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .mx-auto.max-w-4xl {
                max-width: 100%;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            @page {
                margin: 5mm;
                size: A4 portrait;
            }

            .truncate {
                white-space: normal !important;
                overflow: visible !important;
                text-overflow: clip !important;
            }

            .max-w-\\[200px\\] {
                max-width: 100% !important;
            }
        }
    </style>
</div>