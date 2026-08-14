<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Lecture Timetables')] class extends Component {
    // Filters
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $department_id = '';
    public int|string $program_id = '';
    public string $level = '100';
    public int|string $institution_id = '';

    // Modal & Form State
    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public ?int $deletingSlotId = null;
    public ?int $editingId = null;
    public string $allocation_mode = 'allocation'; // 'allocation' or 'course'
    public int|string $selected_allocation_id = '';
    public int|string $selected_course_id = '';
    public int|string $selected_lecturer_id = '';
    public string $day_of_week = 'Monday';
    public int $period_number = 1;
    public string $start_time = '08:00';
    public string $end_time = '10:00';

    // Conflict warnings
    public ?string $conflictWarning = null;

    // View Mode
    public string $view_mode = 'grid'; // 'grid' or 'list'

    // HOD Scoping
    public bool $isHod = false;
    public array $hodDepartmentIds = [];

    public function mount(): void
    {
        Gate::authorize('timetables.view');

        $user = auth()->user();
        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        if (! $user->hasRole('Super Admin') && ! $user->hasRole('Institutional Admin')) {
            $scopedDeptIds = array_merge(
                $user->getScopedModelIds('Academic Secretary', Department::class),
                $user->getScopedModelIds('Head of Department (HOD)', Department::class),
                $user->getScopedModelIds('Exam Officer', Department::class)
            );

            if (! empty($scopedDeptIds)) {
                $this->isHod = true;
                $this->hodDepartmentIds = $scopedDeptIds;
            }

            $staff = Staff::where('email', $user->email)->first();
            if ($staff && $staff->department_id && empty($this->hodDepartmentIds)) {
                $this->isHod = true;
                $this->hodDepartmentIds = [$staff->department_id];
            }
        }

        // Set defaults
        $activeSession = AcademicSession::where('status', 'active')->first()
            ?? AcademicSession::latest()->first();
        if ($activeSession) {
            $this->session_id = $activeSession->id;
        }

        $activeSemester = Semester::where('academic_session_id', $this->session_id)->first();
        if ($activeSemester) {
            $this->semester_id = $activeSemester->id;
        }

        $depts = $this->getDepartmentsProperty();
        if ($depts->isNotEmpty()) {
            $this->department_id = $depts->first()->id;
        }

        $progs = $this->getProgramsProperty();
        if ($progs->isNotEmpty()) {
            $this->program_id = $progs->first()->id;
        }
    }

    public function updatedDepartmentId(): void
    {
        $progs = $this->getProgramsProperty();
        $this->program_id = $progs->isNotEmpty() ? $progs->first()->id : '';
    }

    public function updatedAllocationMode(): void
    {
        $this->selected_allocation_id = '';
        $this->selected_course_id = '';
        $this->selected_lecturer_id = '';
    }

    public function updatedSelectedAllocationId(): void
    {
        if ($this->selected_allocation_id) {
            $alloc = CourseAllocation::find($this->selected_allocation_id);
            if ($alloc) {
                $this->selected_course_id = $alloc->course_id;
                $this->selected_lecturer_id = $alloc->user_id;
            }
        }
    }

    public function updatedPeriodNumber(): void
    {
        $existingSlot = Timetable::where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->where('department_id', $this->department_id)
            ->where('program_id', $this->program_id)
            ->where('level', (string) $this->level)
            ->where('period_number', $this->period_number)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->first();

        if ($existingSlot) {
            $this->start_time = $existingSlot->start_time;
            $this->end_time = $existingSlot->end_time;
            return;
        }

        $periodTimes = [
            1 => ['08:00', '10:00'],
            2 => ['10:00', '12:00'],
            3 => ['12:00', '14:00'],
            4 => ['14:00', '16:00'],
            5 => ['16:00', '18:00'],
            6 => ['18:00', '20:00'],
        ];

        if (isset($periodTimes[$this->period_number])) {
            $this->start_time = $periodTimes[$this->period_number][0];
            $this->end_time = $periodTimes[$this->period_number][1];
        }
    }

    public function openCreateModal(?string $day = 'Monday', ?int $period = 1): void
    {
        Gate::authorize('timetables.create');
        $this->editingId = null;
        $this->day_of_week = $day ?? 'Monday';
        $this->period_number = $period ?? 1;
        $this->updatedPeriodNumber();
        $this->allocation_mode = 'allocation';
        $this->selected_allocation_id = '';
        $this->selected_course_id = '';
        $this->selected_lecturer_id = '';
        $this->conflictWarning = null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('timetables.edit');
        $timetable = Timetable::findOrFail($id);
        $this->editingId = $timetable->id;
        $this->day_of_week = $timetable->day_of_week;
        $this->period_number = $timetable->period_number;
        $this->start_time = $timetable->start_time;
        $this->end_time = $timetable->end_time;
        $this->selected_course_id = $timetable->course_id;
        $this->selected_lecturer_id = $timetable->user_id ?? '';

        if ($timetable->allocatable_type === CourseAllocation::class) {
            $this->allocation_mode = 'allocation';
            $this->selected_allocation_id = $timetable->allocatable_id;
        } else {
            $this->allocation_mode = 'course';
            $this->selected_allocation_id = '';
        }

        $this->conflictWarning = null;
        $this->showModal = true;
    }

    public function saveSlot(): void
    {
        Gate::authorize($this->editingId ? 'timetables.edit' : 'timetables.create');

        $this->validate([
            'session_id' => 'required',
            'semester_id' => 'required',
            'department_id' => 'required',
            'program_id' => 'required',
            'level' => 'required',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'period_number' => 'required|integer|min:1|max:6',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        if ($this->allocation_mode === 'allocation') {
            $this->validate([
                'selected_allocation_id' => 'required|exists:course_allocations,id',
            ]);
            $alloc = CourseAllocation::findOrFail($this->selected_allocation_id);
            $allocatableType = CourseAllocation::class;
            $allocatableId = $alloc->id;
            $courseId = $alloc->course_id;
            $userId = $alloc->user_id;
        } else {
            $this->validate([
                'selected_course_id' => 'required|exists:courses,id',
            ]);
            $course = Course::findOrFail($this->selected_course_id);
            $allocatableType = Course::class;
            $allocatableId = $course->id;
            $courseId = $course->id;
            $userId = $this->selected_lecturer_id ?: null;
        }

        // Check for program & level slot clash
        $existingSlot = Timetable::where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->where('program_id', $this->program_id)
            ->where('level', $this->level)
            ->where('day_of_week', $this->day_of_week)
            ->where('period_number', $this->period_number)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->first();

        if ($existingSlot) {
            $this->conflictWarning = "Time slot conflict! {$this->program_id} Level {$this->level} already has {$existingSlot->course->course_code} scheduled on {$this->day_of_week} Period {$this->period_number}.";
            return;
        }

        // Check for lecturer conflict
        if ($userId) {
            $lecturerConflict = Timetable::where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->where('user_id', $userId)
                ->where('day_of_week', $this->day_of_week)
                ->where('period_number', $this->period_number)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->first();

            if ($lecturerConflict) {
                $lecturerName = User::find($userId)?->name ?? 'Lecturer';
                $this->conflictWarning = "Lecturer conflict! {$lecturerName} is already teaching {$lecturerConflict->course->course_code} on {$this->day_of_week} Period {$this->period_number}.";
                return;
            }
        }

        $institutionId = $this->institution_id ?: (auth()->user()->institution_id ?? 1);

        Timetable::updateOrCreate(
            ['id' => $this->editingId],
            [
                'institution_id' => $institutionId,
                'academic_session_id' => $this->session_id,
                'semester_id' => $this->semester_id,
                'department_id' => $this->department_id,
                'program_id' => $this->program_id,
                'level' => (string) $this->level,
                'allocatable_type' => $allocatableType,
                'allocatable_id' => $allocatableId,
                'course_id' => $courseId,
                'user_id' => $userId,
                'day_of_week' => $this->day_of_week,
                'period_number' => $this->period_number,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
            ]
        );

        // Sync start_time & end_time specifically across all timetable slots matching session, semester, department, program, level & period_number
        Timetable::where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->where('department_id', $this->department_id)
            ->where('program_id', $this->program_id)
            ->where('level', (string) $this->level)
            ->where('period_number', $this->period_number)
            ->update([
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
            ]);

        $this->showModal = false;
        $this->dispatch('notify', message: 'Timetable slot saved successfully.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingSlotId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteSlot(): void
    {
        Gate::authorize('timetables.delete');

        if ($this->deletingSlotId) {
            Timetable::destroy($this->deletingSlotId);
            $this->deletingSlotId = null;
            $this->showDeleteModal = false;
            $this->dispatch('notify', message: 'Timetable slot removed successfully.');
        }
    }

    public function getDepartmentsProperty()
    {
        return Department::query()
            ->when($this->institution_id, fn ($q) => $q->where('institution_id', $this->institution_id))
            ->when($this->isHod, fn ($q) => $q->whereIn('id', $this->hodDepartmentIds))
            ->orderBy('name')
            ->get();
    }

    public function getProgramsProperty()
    {
        return Program::query()
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->orderBy('name')
            ->get();
    }

    public function getSessionsProperty()
    {
        return AcademicSession::query()
            ->orderByDesc('name')
            ->get();
    }

    public function getSemestersProperty()
    {
        return Semester::query()
            ->when($this->session_id, fn ($q) => $q->where('academic_session_id', $this->session_id))
            ->get();
    }

    public function getAvailableAllocationsProperty()
    {
        if (! $this->session_id || ! $this->semester_id) {
            return collect();
        }

        return CourseAllocation::with(['course', 'user'])
            ->where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->when($this->department_id, function ($q) {
                $q->whereHas('course', fn ($c) => $c->where('department_id', $this->department_id));
            })
            ->get();
    }

    public function getAvailableCoursesProperty()
    {
        return Course::query()
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->level, fn ($q) => $q->where('level', $this->level))
            ->orderBy('course_code')
            ->get();
    }

    public function getLecturersProperty()
    {
        return User::whereHas('roles', fn ($r) => $r->whereIn('name', ['Lecturer', 'Head of Department (HOD)', 'Super Admin']))
            ->when($this->institution_id, fn ($q) => $q->where('institution_id', $this->institution_id))
            ->orderBy('name')
            ->get();
    }

    public function getTimetableEntriesProperty()
    {
        if (! $this->session_id || ! $this->semester_id) {
            return collect();
        }

        return Timetable::with(['course', 'user', 'allocatable', 'program', 'department'])
            ->where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->when($this->program_id, fn ($q) => $q->where('program_id', $this->program_id))
            ->when($this->level, fn ($q) => $q->where('level', $this->level))
            ->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon.calendar class="size-7 text-indigo-500" />
                {{ __('Lecture Timetables') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Period-based lecture schedule matrix and management.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- View Mode Switch -->
            <div class="inline-flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
                <button 
                    type="button" 
                    wire:click="$set('view_mode', 'grid')" 
                    class="flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-semibold transition-all {{ $view_mode === 'grid' ? 'bg-white text-indigo-600 shadow dark:bg-zinc-700 dark:text-indigo-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
                >
                    <flux:icon.squares-2x2 class="size-4" />
                    {{ __('Grid View') }}
                </button>

                <button 
                    type="button" 
                    wire:click="$set('view_mode', 'list')" 
                    class="flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-semibold transition-all {{ $view_mode === 'list' ? 'bg-white text-indigo-600 shadow dark:bg-zinc-700 dark:text-indigo-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}"
                >
                    <flux:icon.list-bullet class="size-4" />
                    {{ __('List View') }}
                </button>
            </div>

            <!-- Print Button -->
            <a 
                href="{{ route('cms.timetables.print', ['session_id' => $session_id, 'semester_id' => $semester_id, 'department_id' => $department_id, 'program_id' => $program_id, 'level' => $level]) }}" 
                target="_blank" 
                class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-700 shadow-sm hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
            >
                <flux:icon.printer class="size-4 text-emerald-500" />
                {{ __('Print Timetable') }}
            </a>

            @can('timetables.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal('Monday', 1)">
                    {{ __('Add Slot') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Filters Section -->
    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <flux:label>{{ __('Academic Session') }}</flux:label>
                <flux:select wire:model.live="session_id">
                    <option value="">{{ __('Select Session') }}</option>
                    @foreach ($this->sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>{{ __('Semester') }}</flux:label>
                <flux:select wire:model.live="semester_id">
                    <option value="">{{ __('Select Semester') }}</option>
                    @foreach ($this->semesters as $semester)
                        <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>{{ __('Department') }}</flux:label>
                <flux:select wire:model.live="department_id">
                    <option value="">{{ __('Select Department') }}</option>
                    @foreach ($this->departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>{{ __('Program') }}</flux:label>
                <flux:select wire:model.live="program_id">
                    <option value="">{{ __('Select Program') }}</option>
                    @foreach ($this->programs as $prog)
                        <option value="{{ $prog->id }}">{{ $prog->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:label>{{ __('Level') }}</flux:label>
                <flux:select wire:model.live="level">
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                    <option value="500">500 Level</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Timetable Grid View -->
    @if ($view_mode === 'grid')
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            @php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $periods = range(1, 6);
                $entries = $this->timetableEntries;
            @endphp

            <table class="w-full min-w-[800px] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <th class="p-3 font-semibold text-zinc-600 dark:text-zinc-400 w-32 border-r border-zinc-200 dark:border-zinc-800 text-center">
                            {{ __('Day / Period') }}
                        </th>
                        @foreach ($periods as $pNum)
                            <th class="p-3 font-semibold text-zinc-900 dark:text-white text-center border-r border-zinc-200 dark:border-zinc-800">
                                <div class="font-bold text-indigo-600 dark:text-indigo-400">Period {{ $pNum }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($days as $day)
                        <tr>
                            <td class="p-3 font-bold text-zinc-900 dark:text-white bg-zinc-50/50 dark:bg-zinc-800/30 border-r border-zinc-200 dark:border-zinc-800 text-center">
                                {{ $day }}
                            </td>

                            @foreach ($periods as $pNum)
                                @php
                                    $slot = $entries->first(fn ($e) => strcasecmp($e->day_of_week, $day) === 0 && (int)$e->period_number === (int)$pNum);
                                @endphp
                                <td class="p-2 border-r border-zinc-200 dark:border-zinc-800 vertical-top h-28 w-44">
                                    @if ($slot)
                                        <div class="group relative flex h-full flex-col justify-between rounded-lg border border-indigo-200 bg-indigo-50/70 p-2.5 dark:border-indigo-900/50 dark:bg-indigo-950/40">
                                            <div>
                                                <div class="flex items-center justify-between">
                                                    <span class="font-bold text-indigo-900 dark:text-indigo-200 text-sm">
                                                        {{ $slot->resolved_course?->course_code ?? $slot->course?->course_code }}
                                                    </span>
                                                    @can('timetables.delete')
                                                        <button 
                                                            type="button" 
                                                            wire:click="confirmDelete({{ $slot->id }})" 
                                                            class="opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 transition-opacity"
                                                        >
                                                            <flux:icon.trash class="size-3.5" />
                                                        </button>
                                                    @endcan
                                                </div>
                                                <p class="text-xs text-zinc-700 dark:text-zinc-300 font-medium line-clamp-1 mt-0.5">
                                                    {{ $slot->resolved_course?->title ?? $slot->course?->title }}
                                                </p>
                                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-1">
                                                    <flux:icon.user class="size-3 text-amber-500 shrink-0" />
                                                    <span class="truncate">{{ $slot->resolved_lecturer?->name ?? 'Unassigned' }}</span>
                                                </p>
                                            </div>

                                            <div class="mt-2 flex items-center justify-between pt-1 border-t border-indigo-100 dark:border-indigo-900/30 text-[10px] text-indigo-700 dark:text-indigo-300">
                                                <span>{{ $slot->start_time }} - {{ $slot->end_time }}</span>
                                                @can('timetables.edit')
                                                    <button type="button" wire:click="openEditModal({{ $slot->id }})" class="hover:underline font-semibold">
                                                        Edit
                                                    </button>
                                                @endcan
                                            </div>
                                        </div>
                                    @else
                                        @can('timetables.create')
                                            <button 
                                                type="button" 
                                                wire:click="openCreateModal('{{ $day }}', {{ $pNum }})" 
                                                class="flex h-full w-full flex-col items-center justify-center rounded-lg border border-dashed border-zinc-200 p-2 text-zinc-400 hover:border-indigo-400 hover:bg-indigo-50/30 hover:text-indigo-600 dark:border-zinc-800 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20 dark:hover:text-indigo-400 transition-all group"
                                            >
                                                <flux:icon.plus class="size-4 opacity-50 group-hover:opacity-100" />
                                                <span class="text-[11px] font-medium mt-1">{{ __('Assign') }}</span>
                                            </button>
                                        @else
                                            <div class="flex h-full items-center justify-center text-xs text-zinc-400">
                                                -
                                            </div>
                                        @endcan
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <!-- List View -->
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">{{ __('Day') }}</th>
                            <th class="px-4 py-3">{{ __('Period') }}</th>
                            <th class="px-4 py-3">{{ __('Time Frame') }}</th>
                            <th class="px-4 py-3">{{ __('Course') }}</th>
                            <th class="px-4 py-3">{{ __('Lecturer') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($this->timetableEntries->sortBy(['day_of_week', 'period_number']) as $slot)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                    {{ $slot->day_of_week }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    Period {{ $slot->period_number }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $slot->start_time }} - {{ $slot->end_time }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ $slot->resolved_course?->course_code }} - {{ $slot->resolved_course?->title }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $slot->resolved_lecturer?->name ?? 'Unassigned' }}
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @can('timetables.edit')
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="openEditModal({{ $slot->id }})" />
                                    @endcan
                                    @can('timetables.delete')
                                        <flux:button variant="ghost" size="sm" icon="trash" class="text-rose-600" wire:click="confirmDelete({{ $slot->id }})" />
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                    {{ __('No lecture timetable slots created yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Add/Edit Modal -->
    <flux:modal wire:model="showModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('Edit Timetable Slot') : __('Schedule Timetable Slot') }}
                </flux:heading>
                <flux:subheading>
                    {{ __('Assign course allocation or course to day and period.') }}
                </flux:subheading>
            </div>

            @if ($conflictWarning)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                    <strong>Warning:</strong> {{ $conflictWarning }}
                </div>
            @endif

            <div class="space-y-4">
                <!-- Allocation Method Switch -->
                <div>
                    <flux:label>{{ __('Select Source') }}</flux:label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <label class="flex items-center gap-2 rounded-lg border p-2.5 cursor-pointer text-xs font-semibold {{ $allocation_mode === 'allocation' ? 'border-indigo-500 bg-indigo-50/50 text-indigo-700 dark:border-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="allocation_mode" value="allocation" class="sr-only" />
                            <flux:icon.user-group class="size-4 text-indigo-500" />
                            <span>{{ __('From Allocated Lecturer') }}</span>
                        </label>

                        <label class="flex items-center gap-2 rounded-lg border p-2.5 cursor-pointer text-xs font-semibold {{ $allocation_mode === 'course' ? 'border-indigo-500 bg-indigo-50/50 text-indigo-700 dark:border-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <input type="radio" wire:model.live="allocation_mode" value="course" class="sr-only" />
                            <flux:icon.book-open class="size-4 text-emerald-500" />
                            <span>{{ __('Direct Course Selection') }}</span>
                        </label>
                    </div>
                </div>

                @if ($allocation_mode === 'allocation')
                    <div>
                        <flux:label>{{ __('Assigned Course Allocation') }}</flux:label>
                        <flux:select wire:model.live="selected_allocation_id">
                            <option value="">{{ __('Choose Course Allocation') }}</option>
                            @foreach ($this->availableAllocations as $alloc)
                                <option value="{{ $alloc->id }}">
                                    {{ $alloc->course->course_code }} - {{ $alloc->course->title }} ({{ $alloc->user->name }})
                                </option>
                            @endforeach
                        </flux:select>
                    </div>
                @else
                    <div>
                        <flux:label>{{ __('Course') }}</flux:label>
                        <flux:select wire:model.live="selected_course_id">
                            <option value="">{{ __('Choose Course') }}</option>
                            @foreach ($this->availableCourses as $c)
                                <option value="{{ $c->id }}">{{ $c->course_code }} - {{ $c->title }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>{{ __('Lecturer (Optional)') }}</flux:label>
                        <flux:select wire:model.live="selected_lecturer_id">
                            <option value="">{{ __('Unassigned / Optional') }}</option>
                            @foreach ($this->lecturers as $lect)
                                <option value="{{ $lect->id }}">{{ $lect->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label>{{ __('Day of Week') }}</flux:label>
                        <flux:select wire:model.live="day_of_week">
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:label>{{ __('Period Number') }}</flux:label>
                        <flux:select wire:model.live="period_number">
                            <option value="1">Period 1 (08:00 - 10:00)</option>
                            <option value="2">Period 2 (10:00 - 12:00)</option>
                            <option value="3">Period 3 (12:00 - 14:00)</option>
                            <option value="4">Period 4 (14:00 - 16:00)</option>
                            <option value="5">Period 5 (16:00 - 18:00)</option>
                            <option value="6">Period 6 (18:00 - 20:00)</option>
                        </flux:select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:input label="{{ __('Start Time') }}" wire:model="start_time" type="time" />
                    </div>
                    <div>
                        <flux:input label="{{ __('End Time') }}" wire:model="end_time" type="time" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="saveSlot">
                    {{ __('Save Slot') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal" name="delete-timetable-modal" class="md:max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Timetable Slot') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Are you sure you want to delete this lecture timetable slot? This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteSlot" variant="danger">{{ __('Delete Slot') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
