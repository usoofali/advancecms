<?php

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('My Attendance')] class extends Component
{
    #[Url]
    public string $session_id = '';

    #[Url]
    public string $semester_id = '';

    #[Url]
    public string $student_id = '';

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('view_own_attendance');

        if (! auth()->user()->can('view_all_attendance')) {
            $this->student_id = '';
        }

        $activeSession = AcademicSession::where('status', 'active')->first();
        if (! $this->session_id && $activeSession) {
            $this->session_id = $activeSession->id;
        }

        if (! $this->semester_id) {
            $activeSemester = Semester::where('academic_session_id', $this->session_id)->first();
            if ($activeSemester) {
                $this->semester_id = $activeSemester->id;
            }
        }
    }

    public function getStudentProperty()
    {
        if (auth()->user()->can('view_all_attendance') && $this->student_id) {
            return Student::with(['program.department', 'institution'])->find($this->student_id);
        }

        return Student::where('email', auth()->user()->email)->first();
    }

    public function getStudentsProperty()
    {
        if (! auth()->user()->can('view_all_attendance')) {
            return [];
        }

        $query = Student::query();

        if (auth()->user()->institution_id) {
            $query->where('institution_id', auth()->user()->institution_id);
        }

        return $query->when($this->search, function ($q) {
            $q->where(function ($sq) {
                $sq->where('first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('last_name', 'like', '%'.$this->search.'%')
                    ->orWhere('matric_number', 'like', '%'.$this->search.'%');
            });
        })
            ->limit(10)
            ->get();
    }

    public function getAttendanceStatsProperty()
    {
        $student = $this->student;
        if (! $student) {
            return [];
        }

        $registrations = CourseRegistration::where('student_id', $student->id)
            ->where('academic_session_id', $this->session_id)
            ->when($this->semester_id, fn ($q) => $q->where('semester_id', $this->semester_id))
            ->with('course')
            ->get();

        $stats = [];
        foreach ($registrations as $reg) {
            $stats[] = [
                'course_code' => $reg->course->course_code,
                'course_title' => $reg->course->title,
                'percentage' => $student->getAttendancePercentage($reg->course_id, (int) $this->session_id, $this->semester_id ? (int) $this->semester_id : null),
            ];
        }

        return $stats;
    }

    public function getHistoryProperty()
    {
        $student = $this->student;
        if (! $student) {
            return [];
        }

        return AttendanceRecord::where('student_id', $student->id)
            ->whereHas('attendance.courseAllocation', function ($q) {
                $q->where('academic_session_id', $this->session_id)
                    ->when($this->semester_id, fn ($sq) => $sq->where('semester_id', $this->semester_id));
            })
            ->with(['attendance.courseAllocation.course'])
            ->latest()
            ->get();
    }

    public function render(): View
    {
        $student = $this->student;
        $overallPercentage = $student ? $student->getAttendancePercentage(null, (int) $this->session_id, $this->semester_id ? (int) $this->semester_id : null) : 0;

        return view('pages::cms.attendance.student-view', [
            'overallPercentage' => $overallPercentage,
            'stats' => $this->attendance_stats,
            'history' => $this->history,
            'sessions' => AcademicSession::all(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
        ]);
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My Attendance') }}</flux:heading>
            <flux:subheading>{{ __('Track your lecture participation across all registered courses') }}</flux:subheading>
        </div>

        <div class="flex flex-col md:flex-row items-end gap-2">
            @can('view_all_attendance')
                <div class="relative">
                    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search Student...')" icon="magnifying-glass" class="w-64" />
                    @if($search)
                        <div class="absolute z-50 mt-1 w-full bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                            @forelse($this->students as $s)
                                <button wire:click="$set('student_id', '{{ $s->id }}'); $set('search', '')" class="w-full text-left px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                                    <div class="font-bold text-xs text-zinc-900 dark:text-white">{{ $s->first_name }} {{ $s->last_name }}</div>
                                    <div class="text-[10px] text-zinc-500 font-mono">{{ $s->matric_number }}</div>
                                </button>
                            @empty
                                <div class="p-4 text-xs text-zinc-500 italic">{{ __('No students found.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endcan

            <div class="flex items-center gap-2">
                <flux:select wire:model.live="session_id" class="w-44" :label="__('Academic Session')">
                    @foreach ($sessions as $session)
                        <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="semester_id" class="w-36" :label="__('Semester')">
                    <flux:select.option value="">{{ __('All Semesters') }}</flux:select.option>
                    @foreach ($semesters as $semester)
                        <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    @can('view_all_attendance')
        @if($this->student)
            <flux:card class="mb-8 p-4 bg-zinc-50 dark:bg-zinc-900 flex items-center gap-4">
                <flux:avatar :name="$this->student->first_name" size="lg" />
                <div>
                    <flux:heading size="lg">{{ $this->student->first_name }} {{ $this->student->last_name }}</flux:heading>
                    <flux:subheading size="xs" class="font-mono uppercase">{{ $this->student->matric_number }} • {{ $this->student->program?->name }}</flux:subheading>
                </div>
            </flux:card>
        @endif
    @endcan

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Overall Participation Circular Indicator (Simulated with CSS) -->
        <flux:card class="lg:col-span-1 flex flex-col items-center justify-center p-8 bg-gradient-to-br from-blue-600 to-indigo-700 text-white border-none shadow-xl">
            <div class="relative size-40 flex items-center justify-center mb-6">
                <!-- Outer Ring -->
                <svg class="absolute size-full rotate-[-90deg]">
                    <circle cx="80" cy="80" r="70" fill="transparent" stroke="rgba(255,255,255,0.2)" stroke-width="12" />
                    <circle cx="80" cy="80" r="70" fill="transparent" stroke="white" stroke-width="12" 
                        stroke-dasharray="{{ 2 * pi() * 70 }}" 
                        stroke-dashoffset="{{ (1 - $overallPercentage / 100) * (2 * pi() * 70) }}" 
                        stroke-linecap="round" />
                </svg>
                <div class="text-center">
                    <div class="text-4xl font-black">{{ $overallPercentage }}%</div>
                    <div class="text-[10px] font-bold uppercase tracking-widest opacity-80">{{ __('Overall') }}</div>
                </div>
            </div>
            <flux:heading size="md" class="text-white">{{ __('General Participation') }}</flux:heading>
            <p class="text-sm text-blue-100 mt-2 text-center">{{ __('Your average attendance across all registered courses for the selected period.') }}</p>
        </flux:card>

        <!-- Per Course breakdown -->
        <flux:card class="lg:col-span-2">
            <flux:heading size="md" class="mb-4">{{ __('Course Breakdown') }}</flux:heading>
            <div class="space-y-6">
                @forelse ($stats as $course)
                    <div class="space-y-2">
                        <div class="flex justify-between items-end">
                            <div>
                                <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400 uppercase">{{ $course['course_code'] }}</span>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $course['course_title'] }}</h4>
                            </div>
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">{{ $course['percentage'] }}%</span>
                        </div>
                        <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden border border-zinc-200/50 dark:border-zinc-700/50">
                            <div class="h-full rounded-full transition-all duration-1000 @if($course['percentage'] >= 75) bg-emerald-500 @elseif($course['percentage'] >= 50) bg-amber-500 @else bg-red-500 @endif" 
                                style="width: {{ $course['percentage'] }}%"></div>
                        </div>
                        @if($course['percentage'] < 75)
                            <p class="text-[10px] text-red-500 font-medium italic flex items-center gap-1">
                                <flux:icon.exclamation-circle class="size-3" />
                                {{ __('Below 75% threshold. Ensure better participation to avoid exam sitting issues.') }}
                            </p>
                        @endif
                    </div>
                @empty
                    <div class="py-12 text-center text-zinc-500 italic">
                        {{ __('No registered courses found for this session.') }}
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    <!-- Attendance History List -->
    <flux:card>
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="md">{{ __('Attendance Log') }}</flux:heading>
            <flux:badge variant="neutral" size="sm">{{ count($history) }} {{ __('Sessions') }}</flux:badge>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ __('Course') }}</th>
                        <th class="px-4 py-3 font-semibold text-center text-zinc-900 dark:text-white">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($history as $record)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $record->attendance->date->format('M d, Y') }}</div>
                                <div class="text-[10px] text-zinc-500">{{ $record->attendance->date->format('l') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $record->attendance->courseAllocation->course->course_code }}</div>
                                <div class="text-xs text-zinc-500 truncate max-w-xs">{{ $record->attendance->courseAllocation->course->title }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($record->is_present)
                                    <flux:badge color="green" size="sm" class="min-w-[80px]">{{ __('Present') }}</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="min-w-[80px]">{{ __('Absent') }}</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-12 text-center text-zinc-500 italic">
                                <flux:icon.calendar class="mx-auto size-8 mb-3 opacity-20" />
                                <p>{{ __('No attendance logs recorded for this period.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
