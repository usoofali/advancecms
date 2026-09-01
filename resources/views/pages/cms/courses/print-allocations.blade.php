<?php

use App\Models\AcademicSession;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Print Course Allocations')] class extends Component {
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $department_id = '';
    public int|string $program_id = '';
    public int|string $level = '';
    public int|string $institution_id = '';

    public ?Institution $institution = null;

    public function mount(): void
    {
        Gate::authorize('courses.allocate');

        $this->session_id = request('session_id', '');
        $this->semester_id = request('semester_id', '');
        $this->department_id = request('department_id', '');
        $this->program_id = request('program_id', '');
        $this->level = request('level', '');
        $this->institution_id = request('institution_id', auth()->user()->institution_id ?? '');

        $instId = $this->institution_id ?: auth()->user()->institution_id;
        if ($instId && $instId !== 'null') {
            $this->institution = Institution::find($instId);
        }
    }

    public function allocations()
    {
        $query = CourseAllocation::with([
            'user',
            'course.department',
            'course.program',
            'academicSession',
            'semester',
        ])->latest('course_allocations.created_at');

        if ($this->institution_id && $this->institution_id !== 'null') {
            $query->where('course_allocations.institution_id', $this->institution_id);
        }

        if (($this->department_id && $this->department_id !== 'null') || ($this->program_id && $this->program_id !== 'null') || ($this->level && $this->level !== 'null')) {
            $query->join('courses', 'course_allocations.course_id', '=', 'courses.id')
                ->select('course_allocations.*');

            if ($this->department_id && $this->department_id !== 'null') {
                $query->where('courses.department_id', $this->department_id);
            }
            if ($this->program_id && $this->program_id !== 'null') {
                $query->where('courses.program_id', $this->program_id);
            }
            if ($this->level && $this->level !== 'null') {
                $query->where('courses.level', $this->level);
            }
        }

        if ($this->session_id && $this->session_id !== 'null') {
            $query->where('course_allocations.academic_session_id', $this->session_id);
        }

        if ($this->semester_id && $this->semester_id !== 'null') {
            $query->where('course_allocations.semester_id', $this->semester_id);
        }

        return $query->get();
    }

    public function hasSessionFilter(): bool
    {
        return !empty($this->session_id) && $this->session_id !== 'null';
    }

    public function hasSemesterFilter(): bool
    {
        return !empty($this->semester_id) && $this->semester_id !== 'null';
    }

    public function hasDepartmentFilter(): bool
    {
        return (!empty($this->department_id) && $this->department_id !== 'null') || (!empty($this->program_id) && $this->program_id !== 'null');
    }

    public function hasLevelFilter(): bool
    {
        return !empty($this->level) && $this->level !== 'null';
    }

    public function getHeaderFilterSummary(): string
    {
        $parts = [];
        if ($this->hasSessionFilter()) {
            $session = AcademicSession::find($this->session_id);
            if ($session) {
                $parts[] = 'SESSION: ' . $session->name;
            }
        }
        if ($this->hasSemesterFilter()) {
            $semester = Semester::find($this->semester_id);
            if ($semester) {
                $parts[] = 'SEMESTER: ' . strtoupper($semester->name);
            }
        }
        if (!empty($this->department_id) && $this->department_id !== 'null') {
            $dept = Department::find($this->department_id);
            if ($dept) {
                $parts[] = 'DEPARTMENT: ' . strtoupper($dept->name);
            }
        }
        if (!empty($this->program_id) && $this->program_id !== 'null') {
            $prog = Program::find($this->program_id);
            if ($prog) {
                $parts[] = 'PROGRAM: ' . strtoupper($prog->name);
            }
        }
        if ($this->hasLevelFilter()) {
            $parts[] = 'LEVEL: ' . $this->level . 'L';
        }

        return !empty($parts) ? implode(' | ', $parts) : 'ALL ALLOCATIONS';
    }
};
?>

<div class="p-8 bg-white min-h-screen text-black font-sans">
    <div class="flex flex-col items-center mb-6 border-b-2 border-black pb-4 text-center">
        @if($this->institution?->logo_path)
            <img src="{{ asset('storage/' . $this->institution->logo_path) }}" class="h-20 w-20 object-contain mb-3" alt="Institution Logo">
        @endif
        <h1 class="text-2xl font-black uppercase tracking-tight">{{ $this->institution?->name ?? config('app.name') }}</h1>
        <h2 class="text-lg font-bold uppercase tracking-widest text-zinc-700 mt-1">Course Allocation Report</h2>
        <div class="mt-2 text-sm font-semibold uppercase tracking-wider text-zinc-900 bg-zinc-100 px-3 py-1 rounded">
            {{ $this->getHeaderFilterSummary() }}
        </div>
        <div class="mt-2 text-xs text-zinc-500">
            Generated on: {{ now()->format('M d, Y H:i A') }}
        </div>
    </div>

    @php
        $showDeptCol = !$this->hasDepartmentFilter();
        $showLevelCol = !$this->hasLevelFilter();
        $showSessionCol = !$this->hasSessionFilter() || !$this->hasSemesterFilter();
        
        $colCount = 5;
        if ($showDeptCol) $colCount++;
        if ($showLevelCol) $colCount++;
        if ($showSessionCol) $colCount++;
    @endphp

    <table class="w-full text-sm border-collapse border border-zinc-400">
        <thead>
            <tr class="bg-zinc-100 uppercase text-xs font-bold text-zinc-800">
                <th class="border border-zinc-400 px-3 py-2 text-center w-12">#</th>
                <th class="border border-zinc-400 px-3 py-2 text-left">Course Code</th>
                <th class="border border-zinc-400 px-3 py-2 text-left">Course Title</th>
                <th class="border border-zinc-400 px-3 py-2 text-center w-16">Units</th>
                <th class="border border-zinc-400 px-3 py-2 text-left">Allocated Lecturer</th>
                @if($showDeptCol)
                    <th class="border border-zinc-400 px-3 py-2 text-left">Department & Program</th>
                @endif
                @if($showLevelCol)
                    <th class="border border-zinc-400 px-3 py-2 text-center w-20">Level</th>
                @endif
                @if($showSessionCol)
                    <th class="border border-zinc-400 px-3 py-2 text-left">
                        @if($this->hasSessionFilter())
                            Semester
                        @elseif($this->hasSemesterFilter())
                            Academic Session
                        @else
                            Session & Semester
                        @endif
                    </th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-300">
            @forelse($this->allocations() as $index => $alloc)
                <tr class="hover:bg-zinc-50">
                    <td class="border border-zinc-400 px-3 py-2 text-center font-medium">{{ $index + 1 }}</td>
                    <td class="border border-zinc-400 px-3 py-2 font-mono font-bold uppercase">{{ $alloc->course->course_code }}</td>
                    <td class="border border-zinc-400 px-3 py-2 font-medium">{{ $alloc->course->title }}</td>
                    <td class="border border-zinc-400 px-3 py-2 text-center">{{ $alloc->course->credit_unit ?? '-' }}</td>
                    <td class="border border-zinc-400 px-3 py-2 font-bold uppercase">{{ $alloc->user->name }}</td>
                    @if($showDeptCol)
                        <td class="border border-zinc-400 px-3 py-2 text-xs">
                            <div class="font-semibold uppercase">{{ $alloc->course->department?->name ?? 'N/A' }}</div>
                            <div class="text-zinc-600 uppercase">{{ $alloc->course->program?->name ?? 'N/A' }}</div>
                        </td>
                    @endif
                    @if($showLevelCol)
                        <td class="border border-zinc-400 px-3 py-2 text-center font-bold">{{ $alloc->course->level ? $alloc->course->level . 'L' : '-' }}</td>
                    @endif
                    @if($showSessionCol)
                        <td class="border border-zinc-400 px-3 py-2 text-xs">
                            @if($this->hasSessionFilter())
                                <div class="font-semibold uppercase">{{ ucfirst($alloc->semester->name) }}</div>
                            @elseif($this->hasSemesterFilter())
                                <div class="font-semibold">{{ $alloc->academicSession->name }}</div>
                            @else
                                <div class="font-semibold">{{ $alloc->academicSession->name }}</div>
                                <div class="text-zinc-600 uppercase">{{ ucfirst($alloc->semester->name) }}</div>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colCount }}" class="border border-zinc-400 px-3 py-6 text-center text-zinc-500 italic">
                        No course allocations found matching the selected criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-12 pt-8 flex justify-between text-sm">
        <div class="border-t border-black w-56 text-center pt-2 italic font-medium">
            Head of Department Signature & Date
        </div>
        <div class="border-t border-black w-56 text-center pt-2 italic font-medium">
            Dean / Registrar Signature & Date
        </div>
    </div>

    <style>
        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
                background-color: white !important;
                background-image: none !important;
                color: black !important;
            }

            @page {
                margin: 1cm;
                size: portrait;
            }
        }
    </style>

    <div class="mt-10 no-print flex justify-center gap-4">
        <button onclick="window.print()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-md transition-colors flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H7a2 2 0 00-2 2v4h14z" />
            </svg>
            Print Allocation Report
        </button>
        <button onclick="window.close()" class="px-6 py-2 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 font-bold rounded-lg transition-colors">
            Close Window
        </button>
    </div>
</div>
