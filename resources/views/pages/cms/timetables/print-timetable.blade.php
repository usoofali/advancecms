<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Timetable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Print Lecture Timetable')] class extends Component {
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $department_id = '';
    public int|string $program_id = '';
    public string $level = '100';
    public bool $personal = false;

    public ?Institution $institution = null;
    public ?AcademicSession $sessionModel = null;
    public ?Semester $semesterModel = null;
    public ?Department $departmentModel = null;
    public ?Program $programModel = null;

    public function mount(): void
    {
        $this->session_id = request('session_id', '');
        $this->semester_id = request('semester_id', '');
        $this->department_id = request('department_id', '');
        $this->program_id = request('program_id', '');
        $this->level = request('level', '100');
        $this->personal = (bool) request('personal', false);

        $user = auth()->user();

        if (! $this->personal) {
            Gate::authorize('timetables.view');
        } else {
            Gate::authorize('timetables.view_personal');
        }

        $instId = $user->institution_id ?? 1;
        $this->institution = Institution::find($instId);

        if ($this->session_id) {
            $this->sessionModel = AcademicSession::find($this->session_id);
        }
        if ($this->semester_id) {
            $this->semesterModel = Semester::find($this->semester_id);
        }
        if ($this->department_id) {
            $this->departmentModel = Department::find($this->department_id);
        }
        if ($this->program_id) {
            $this->programModel = Program::find($this->program_id);
        }
    }

    public function getTimetableEntriesProperty()
    {
        $user = auth()->user();

        $query = Timetable::with(['course', 'user', 'allocatable', 'program', 'department'])
            ->when($this->session_id, fn ($q) => $q->where('academic_session_id', $this->session_id))
            ->when($this->semester_id, fn ($q) => $q->where('semester_id', $this->semester_id));

        if ($this->personal) {
            if ($user->hasRole('Student')) {
                return $query->forStudent($user)->get();
            }
            if ($user->hasRole('Lecturer')) {
                return $query->forLecturer($user)->get();
            }
        }

        return $query
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->program_id, fn ($q) => $q->where('program_id', $this->program_id))
            ->when($this->level, fn ($q) => $q->where('level', $this->level))
            ->get();
    }
}; ?>

<div class="min-h-screen bg-white p-8 text-black print:p-0">
    <!-- Action Bar (Hidden when printing) -->
    <div class="mb-6 flex items-center justify-between border-b pb-4 print:hidden">
        <a href="javascript:history.back()" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-900">
            &larr; Back
        </a>
        <button onclick="window.print()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
            Print Schedule
        </button>
    </div>

    <!-- Letterhead -->
    <div class="mb-6 text-center">
        @if ($institution && $institution->logo_url)
            <img src="{{ $institution->logo_url }}" alt="Logo" class="mx-auto mb-2 h-16 w-auto object-contain" />
        @endif
        <h1 class="text-2xl font-bold uppercase tracking-wider text-gray-900">
            {{ $institution->name ?? config('app.name') }}
        </h1>
        <h2 class="text-lg font-semibold text-gray-700">
            {{ $personal ? 'PERSONAL LECTURE TIMETABLE SCHEDULE' : 'OFFICIAL LECTURE TIMETABLE' }}
        </h2>
        <div class="mt-2 flex flex-wrap justify-center gap-x-6 text-sm text-gray-600 font-medium">
            <span><strong>Session:</strong> {{ $sessionModel->name ?? 'All Sessions' }}</span>
            <span><strong>Semester:</strong> {{ $semesterModel->name ?? 'All Semesters' }}</span>
            @if ($departmentModel)
                <span><strong>Department:</strong> {{ $departmentModel->name }}</span>
            @endif
            @if ($programModel)
                <span><strong>Program:</strong> {{ $programModel->name }}</span>
            @endif
            @if ($level && $level !== 'null')
                <span><strong>Level:</strong> {{ $level }}</span>
            @endif
            @if ($personal)
                <span><strong>User:</strong> {{ auth()->user()->name }} ({{ auth()->user()->email }})</span>
            @endif
        </div>
    </div>

    <!-- Period Matrix Table -->
    @php
        $entries = $this->timetableEntries;

        // Truncate period columns to max allocated period (e.g. if period 3 is highest allocated, show 1..3)
        $maxAllocatedPeriod = $entries->max(fn ($e) => (int) $e->period_number) ?: 1;
        $periods = range(1, max(1, $maxAllocatedPeriod));

        // Filter days: omit Saturday if no courses are allocated on Saturday
        $allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $hasSaturday = $entries->contains(fn ($e) => strcasecmp($e->day_of_week, 'Saturday') === 0);
        $days = $hasSaturday ? array_merge($allDays, ['Saturday']) : $allDays;
    @endphp

    <table class="w-full border-collapse border border-gray-900 text-xs">
        <thead>
            <tr class="bg-gray-100 border-b border-gray-900">
                <th class="border border-gray-900 p-2 text-center font-bold w-24">Day / Period</th>
                @foreach ($periods as $pNum)
                    <th class="border border-gray-900 p-2 text-center font-bold">
                        <div>Period {{ $pNum }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
                <tr class="border-b border-gray-900">
                    <td class="border border-gray-900 p-2 font-bold text-center bg-gray-50">
                        {{ $day }}
                    </td>
                    @foreach ($periods as $pNum)
                        @php
                            $slot = $entries->first(fn ($e) => strcasecmp($e->day_of_week, $day) === 0 && (int)$e->period_number === (int)$pNum);
                        @endphp
                        <td class="border border-gray-900 p-2 text-left vertical-top h-20 w-36">
                            @if ($slot)
                                <div class="font-bold text-sm text-black">
                                    {{ $slot->resolved_course?->course_code ?? $slot->course?->course_code }}
                                </div>
                                <div class="text-[11px] font-medium text-gray-800 line-clamp-1">
                                    {{ $slot->resolved_course?->title ?? $slot->course?->title }}
                                </div>
                                <div class="text-[10px] text-gray-600 mt-1">
                                    Lec: {{ $slot->resolved_lecturer?->name ?? 'Unassigned' }}
                                </div>
                                <div class="text-[9px] text-gray-500 italic mt-0.5">
                                    {{ $slot->start_time }} - {{ $slot->end_time }}
                                </div>
                            @else
                                <div class="text-center text-gray-300">-</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Footer Signatures -->
    <div class="mt-12 grid grid-cols-2 gap-8 text-xs">
        <div>
            <div class="border-t border-gray-400 pt-1 text-center font-semibold">
                Timetable Officer / HOD Signature & Date
            </div>
        </div>
        <div>
            <div class="border-t border-gray-400 pt-1 text-center font-semibold">
                Dean of Academics Signature & Date
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

            body {
                background-color: white !important;
                background-image: none !important;
                color: black !important;
            }

            @page {
                margin: 1cm;
                size: landscape;
            }
        }
    </style>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.print();
        });
    </script>
</div>
