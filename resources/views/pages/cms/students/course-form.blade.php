<?php

use App\Models\Student;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\CourseRegistration;
use App\Models\RegistrationStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Course Form')] class extends Component {
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

    public function with(): array
    {
        $registrations = collect();
        $regStatus = null;
        $session = null;
        $semester = null;

        if ($this->student && $this->session_id && $this->semester_id) {
            $session = AcademicSession::find($this->session_id);
            $semester = Semester::find($this->semester_id);
            
            $registrations = CourseRegistration::with('course')
                ->where('student_id', $this->student->id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->get();

            $regStatus = RegistrationStatus::where('student_id', $this->student->id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->first();
        }

        return [
            'sessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'registrations' => $registrations,
            'regStatus' => $regStatus,
            'session' => $session,
            'semester' => $semester,
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-6">
    {{-- Filter UI (Hidden in Print) --}}
    <flux:card class="print:hidden space-y-6">
        <flux:heading size="lg">{{ __('Course Registration Form') }}</flux:heading>
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
        <div class="flex justify-end">
            <flux:button icon="printer" variant="primary" onclick="window.print()">
                {{ __('Print Course Form') }}
            </flux:button>
        </div>
        @endif
    </flux:card>

    @if ($student && $session_id && $semester_id)
    {{-- Printable Form --}}
    <div
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 sm:p-6 shadow-sm rounded-xl print:shadow-none print:border-none print:p-0">
        {{-- Form Header --}}
        <div class="text-center space-y-2 mb-6">
            @php $inst = $student->program->department->institution; @endphp
            @if ($inst->logo_path)
            <img src="{{ asset('storage/'.$inst->logo_path) }}" class="h-16 mx-auto mb-1" alt="Institution Logo">
            @endif
            <h1 class="text-xl font-black uppercase tracking-tight text-zinc-900 dark:text-white">{{ $inst->name }}
            </h1>
            <h2 class="text-lg font-bold uppercase text-zinc-700 dark:text-zinc-300">{{ __('Course Registration Form')
                }}</h2>
            <div class="flex items-center justify-center gap-3 text-xs font-semibold text-zinc-500">
                <span>{{ $session?->name }}</span>
                <span>&bull;</span>
                <span class="uppercase">{{ $semester?->name }} {{ __('Semester') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            {{-- Profile Info --}}
            <div class="space-y-1">
                <div class="flex border-b border-zinc-100 dark:border-zinc-800 pb-0.5">
                    <span class="w-28 font-bold text-zinc-500 uppercase text-[9px] tracking-widest">{{ __('Full Name') }}</span>
                    <span class="font-semibold text-xs text-zinc-900 dark:text-white uppercase">{{ $student->full_name }}</span>
                </div>
                <div class="flex border-b border-zinc-100 dark:border-zinc-800 pb-0.5">
                    <span class="w-28 font-bold text-zinc-500 uppercase text-[9px] tracking-widest">{{ __('Matric No') }}</span>
                    <span class="font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $student->matric_number }}</span>
                </div>
                <div class="flex border-b border-zinc-100 dark:border-zinc-800 pb-0.5">
                    <span class="w-28 font-bold text-zinc-500 uppercase text-[9px] tracking-widest">{{ __('Department') }}</span>
                    <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $student->program->department->name }}</span>
                </div>
                <div class="flex border-b border-zinc-100 dark:border-zinc-800 pb-0.5">
                    <span class="w-28 font-bold text-zinc-500 uppercase text-[9px] tracking-widest">{{ __('Program') }}</span>
                    <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $student->program->name }}</span>
                </div>
                <div class="flex border-b border-zinc-100 dark:border-zinc-800 pb-0.5">
                    <span class="w-28 font-bold text-zinc-500 uppercase text-[9px] tracking-widest">{{ __('Level') }}</span>
                    <span class="text-xs text-zinc-900 dark:text-white font-bold">{{ $student->currentLevel($session) }}</span>
                </div>
            </div>
        </div>

        {{-- Course Table --}}
        <div class="space-y-2 mb-8">
            <h3
                class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500 border-b-2 border-zinc-900 dark:border-white pb-0.5 inline-block">
                {{ __('Registered Courses') }}
            </h3>
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-zinc-100 dark:bg-zinc-800 print:bg-zinc-100">
                        <th class="py-1 px-2 text-left border border-zinc-200 dark:border-zinc-700 uppercase leading-none font-black text-[9px]">
                            {{ __('Code') }}</th>
                        <th class="py-1 px-2 text-left border border-zinc-200 dark:border-zinc-700 uppercase leading-none font-black text-[9px]">
                            {{ __('Course Title') }}</th>
                        <th class="py-1 px-2 text-center border border-zinc-200 dark:border-zinc-700 uppercase leading-none font-black text-[9px]">
                            {{ __('Units') }}</th>
                        <th class="py-1 px-2 text-center border border-zinc-200 dark:border-zinc-700 uppercase leading-none font-black text-[9px]">
                            {{ __('Type') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registrations as $reg)
                    <tr>
                        <td class="py-1 px-2 border border-zinc-200 dark:border-zinc-700 font-mono font-bold text-[10px]">{{ $reg->course->course_code }}</td>
                        <td class="py-1 px-2 border border-zinc-200 dark:border-zinc-700 text-[10px] truncate max-w-[200px]">{{ $reg->course->title }}</td>
                        <td class="py-1 px-2 border border-zinc-200 dark:border-zinc-700 text-center font-bold text-[10px]">{{ $reg->course->credit_unit }}</td>
                        <td class="py-1 px-2 border border-zinc-200 dark:border-zinc-700 text-center uppercase text-[9px]">
                            {{ $reg->course->course_type ?? 'C' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-bold bg-zinc-50 print:bg-zinc-50">
                        <td colspan="2" class="py-1 px-2 text-right border border-zinc-200 dark:border-zinc-700 uppercase text-[9px] tracking-widest text-zinc-600 print:text-black">
                            {{ __('Total Registered Units:') }}</td>
                        <td class="py-1 px-2 border border-zinc-200 dark:border-zinc-700 text-center text-xs">
                            {{ $registrations->sum(fn($r) => $r->course->credit_unit) }}</td>
                        <td class="border border-zinc-200 dark:border-zinc-700"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Signatures --}}
        <div class="grid grid-cols-2 gap-8 pt-6">
            <div class="text-center space-y-8">
                <div class="border-t border-zinc-300 dark:border-zinc-600 pt-1.5">
                    <p class="font-bold uppercase text-[9px] tracking-widest">{{ __('Student Signature & Date') }}</p>
                </div>
            </div>
            <div class="text-center space-y-8">
                <div class="border-t border-zinc-300 dark:border-zinc-600 pt-1.5">
                    <p class="font-bold uppercase text-[9px] tracking-widest">{{ __('HOD Signature, Stamp & Date') }}
                    </p>
                    @if ($regStatus?->status === 'closed')
                    <div
                        class="mt-1 inline-flex items-center gap-1 text-[9px] text-green-600 dark:text-green-400 font-black uppercase bg-green-50 dark:bg-green-900/20 px-2 py-0.5 rounded border border-green-200 dark:border-green-800 print:hidden">
                        <flux:icon.check-circle variant="micro" class="size-3" />
                        {{ __('Registration Locked') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-[8px] text-zinc-400 uppercase tracking-widest italic border-t border-zinc-100 pt-2">
            {{ __('This document was generated by the Academic Management System on') }} {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    @else
    <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
        {{ __('Please select a session and semester to generate the course form.') }}
    </div>
    @endif
</div>