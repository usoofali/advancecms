<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Services\GradingService;
use App\Models\RegistrationStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Course Registration')] class extends Component {
    public int|string $student_id = '';
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public array $selected_courses = [];
    public array $courses_to_drop = [];
    public int|string $institution_id = '';

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedSessionId(): void
    {
        $this->semester_id = '';
    }

    public function register(): void
    {
        $this->validate([
            'student_id'       => ['required', 'exists:students,id'],
            'session_id'       => ['required', 'exists:academic_sessions,id'],
            'semester_id'      => ['required', 'exists:semesters,id'],
            'selected_courses' => ['required', 'array', 'min:1'],
        ]);

        // Check if registration is locked
        $isClosed = false;
        if ($this->session_id && $this->semester_id) {
            $regStatus = RegistrationStatus::where('student_id', $this->student_id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->first();
            $isClosed = $regStatus && $regStatus->isClosed();
        }

        if ($isClosed) {
            $this->addError('selected_courses', 'Registration is closed for this student/semester.');
            return;
        }

        // Verify all mandatory carryover courses are included
        $student = Student::find($this->student_id);
        $carryoverCourses = app(GradingService::class)->getCarryoverCourses(
            $student,
            (int) $this->institution_id,
            (int) $this->session_id,
            (int) $this->semester_id
        );
        $missingCarryovers = $carryoverCourses->whereNotIn('id', $this->selected_courses);

        if ($missingCarryovers->isNotEmpty()) {
            $this->addError('selected_courses', 'You must include all mandatory carryover courses: ' .
                $missingCarryovers->pluck('course_code')->implode(', '));

            return;
        }

        $carryoverIds = $carryoverCourses->pluck('id')->toArray();
        $newlyRegistered = 0;
        $alreadyRegistered = 0;

        foreach ($this->selected_courses as $courseId) {
            $exists = CourseRegistration::query()
                ->where('institution_id', $this->institution_id)
                ->where('student_id', $this->student_id)
                ->where('course_id', $courseId)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->exists();

            if ($exists) {
                $alreadyRegistered++;
            } else {
                CourseRegistration::create([
                    'institution_id'      => $this->institution_id,
                    'student_id'          => $this->student_id,
                    'course_id'           => $courseId,
                    'academic_session_id' => $this->session_id,
                    'semester_id'         => $this->semester_id,
                    'status'              => 'registered',
                    'is_carryover'        => in_array($courseId, $carryoverIds),
                ]);
                $newlyRegistered++;
            }
        }

        $this->reset(['selected_courses']);

        if ($newlyRegistered > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully registered {$newlyRegistered} course(s).",
            ]);
        }

        if ($alreadyRegistered > 0) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "{$alreadyRegistered} course(s) were already registered.",
            ]);
        }
    }

    public function drop(): void
    {
        $this->validate([
            'student_id'      => ['required', 'exists:students,id'],
            'session_id'      => ['required', 'exists:academic_sessions,id'],
            'semester_id'     => ['required', 'exists:semesters,id'],
            'courses_to_drop' => ['required', 'array', 'min:1'],
        ]);

        // Check if registration is locked
        $isClosed = false;
        if ($this->session_id && $this->semester_id) {
            $regStatus = RegistrationStatus::where('student_id', $this->student_id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->first();
            $isClosed = $regStatus && $regStatus->isClosed();
        }

        if ($isClosed) {
            $this->addError('courses_to_drop', 'Registration is closed for this student/semester.');
            return;
        }

        $dropped = CourseRegistration::query()
            ->where('institution_id', $this->institution_id)
            ->where('student_id', $this->student_id)
            ->where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->whereIn('course_id', $this->courses_to_drop)
            ->delete();

        $this->reset(['courses_to_drop']);

        if ($dropped > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully dropped {$dropped} course(s).",
            ]);
        }
    }

    public function render(): \Illuminate\View\View
    {
        $student = $this->student_id ? Student::with('program')->find($this->student_id) : null;
        $availableCourses = collect();
        $registeredCourses = collect();
        $carryoverCourses = collect();
        $currentLevel = null;

        if ($student && $this->semester_id) {
            $session = AcademicSession::find($this->session_id);
            $semester = Semester::find($this->semester_id);

            $currentLevel = $student->currentLevel($session);

            $allLevelCourses = Course::query()
                ->where('institution_id', $this->institution_id)
                ->where('program_id', $student->program_id)
                ->where('level', $currentLevel)
                ->where('semester', $semester->name === 'first' ? 1 : 2)
                ->get();

            $registeredCourseIds = CourseRegistration::query()
                ->where('institution_id', $this->institution_id)
                ->where('student_id', $this->student_id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $this->semester_id)
                ->pluck('course_id')
                ->toArray();

            // Detect mandatory carryover courses not yet registered
            $carryoverCourses = app(GradingService::class)->getCarryoverCourses(
                $student,
                (int) $this->institution_id,
                (int) $this->session_id,
                (int) $this->semester_id
            );

            $carryoverIds = $carryoverCourses->pluck('id');

            // Available = level courses excluding registered and carryovers
            $availableCourses = $allLevelCourses
                ->whereNotIn('id', $registeredCourseIds)
                ->whereNotIn('id', $carryoverIds->all());

            $registeredCourses = $allLevelCourses->whereIn('id', $registeredCourseIds);
        }

        return view('pages.cms.students.registration', [
            'institutions' => auth()->user()->institution_id
                ? []
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'students'          => Student::query()
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->orderBy('last_name')
                ->get(),
            'sessions'          => AcademicSession::query()->where('status', 'active')->get(),
            'semesters'         => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'carryoverCourses'  => $carryoverCourses,
            'availableCourses'  => $availableCourses,
            'registeredCourses' => $registeredCourses,
            'currentLevel'      => $currentLevel,
            'isClosed'          => ($this->student_id && $this->session_id && $this->semester_id)
                                    ? RegistrationStatus::where('student_id', $this->student_id)
                                        ->where('academic_session_id', $this->session_id)
                                        ->where('semester_id', $this->semester_id)
                                        ->where('status', 'closed')
                                        ->exists()
                                    : false,
        ]);
    }
}; ?>



<div class="mx-auto max-w-4xl">
    <div class="mb-8 items-center justify-between flex">
        <div>
            <flux:heading size="xl">{{ __('Course Registration') }}</flux:heading>
            <flux:subheading>{{ __('Enroll students into courses for a semester') }}</flux:subheading>
        </div>
        <x-action-message on="registration-success">
            <flux:badge color="green">{{ __('Registration successful!') }}</flux:badge>
        </x-action-message>
    </div>

    <flux:card class="space-y-8">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
            @if (!auth()->user()->institution_id)
            <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                @foreach ($institutions as $inst)
                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <flux:select wire:model.live="student_id" :label="__('Student')" required :disabled="!$institution_id">
                <flux:select.option value="null">{{ __('Select student...') }}</flux:select.option>
                @foreach ($students as $stu)
                <flux:select.option :value="$stu->id">{{ $stu->full_name }} ({{ $stu->matric_number }})
                </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="session_id" :label="__('Academic Session')" required>
                <flux:select.option value="null">{{ __('Select session...') }}</flux:select.option>
                @foreach ($sessions as $session)
                <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" required :disabled="!$session_id">
                <flux:select.option value="null">{{ __('Select semester...') }}</flux:select.option>
                @foreach ($semesters as $semester)
                <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($isClosed)
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 text-red-700">
            <flux:icon.lock-closed class="size-5" />
            <div class="text-sm font-semibold uppercase tracking-tight">{{ __('Registration is closed and verified for
                this student.') }}</div>
        </div>
        @endif

        @if ($student_id && $semester_id)
        <div class="space-y-8">
            <!-- Carryover Courses Section -->
            @if ($carryoverCourses->isNotEmpty())
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md" class="text-red-600 dark:text-red-400">
                        {{ __('Mandatory Carryover Courses') }}
                    </flux:heading>
                    <flux:badge color="red" size="sm" inset="top bottom">
                        {{ __('Action Required') }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($carryoverCourses as $course)
                    <label
                        class="flex items-center p-4 border rounded-lg bg-red-50/30 dark:bg-red-900/10 border-red-200 dark:border-red-900/30">
                        <div
                            class="flex items-center justify-center w-5 h-5 me-3 rounded border border-red-400 bg-red-500 text-white">
                            <flux:icon.check variant="micro" />
                        </div>
                        <input type="checkbox" wire:model="selected_courses" value="{{ $course->id }}" class="hidden"
                            checked disabled />
                        <div>
                            <div class="font-mono text-sm font-bold text-red-700 dark:text-red-400">{{
                                $course->course_code }}</div>
                            <div class="text-xs text-red-600/80 dark:text-red-500/80">{{ $course->title }} ({{
                                $course->credit_unit }} units)</div>
                        </div>
                    </label>
                    {{-- We need to ensure these are in the selected_courses array on component load if not already --}}
                    @php
                    if(!in_array($course->id, $selected_courses)) {
                    $selected_courses[] = $course->id;
                    }
                    @endphp
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Available Courses Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Available Courses') }}</flux:heading>
                    @if ($currentLevel)
                    <flux:badge color="blue" size="sm">
                        {{ __('Level') }} {{ $currentLevel }}
                    </flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($availableCourses as $course)
                    <label
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model="selected_courses" :value="$course->id" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course->course_code }}</div>
                            <div class="text-sm">{{ $course->title }}</div>
                            <div class="text-xs text-zinc-500">{{ $course->credit_unit }} {{ __('Units') }}</div>
                        </div>
                    </label>
                    @empty
                    <div class="col-span-full p-8 text-center border-2 border-dashed rounded-xl text-zinc-500">
                        {{ __('No more recommended courses available to register for this semester.') }}
                    </div>
                    @endforelse
                </div>

                @if (count($availableCourses) > 0)
                <div class="flex flex-col items-end pt-2">
                    <flux:button variant="primary" wire:click="register" :disabled="$isClosed">
                        {{ __('Register Selected Courses') }}
                    </flux:button>
                    <flux:error name="selected_courses" class="mt-2" />
                </div>
                @endif
            </div>

            <flux:separator />

            <!-- Registered Courses Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Currently Registered') }}</flux:heading>
                    @if (count($registeredCourses) > 0)
                    <flux:badge color="green" size="sm">
                        {{ __('Total Units:') }} {{ $registeredCourses->sum('credit_unit') }}
                    </flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($registeredCourses as $course)
                    <label
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model="courses_to_drop" :value="$course->id" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course->course_code }}</div>
                            <div class="text-sm">{{ $course->title }}</div>
                            <div class="flex gap-2 items-center mt-1">
                                <flux:badge size="sm" color="green">{{ __('Registered') }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $course->credit_unit }} {{ __('Units') }}</span>
                            </div>
                        </div>
                    </label>
                    @empty
                    <div class="col-span-full p-8 text-center border-2 border-dashed rounded-xl text-zinc-500">
                        {{ __('No courses registered yet.') }}
                    </div>
                    @endforelse
                </div>

                @if (count($registeredCourses) > 0)
                <div class="flex flex-col items-end pt-2">
                    <flux:button variant="danger" wire:click="drop" :disabled="$isClosed">
                        {{ __('Drop Selected Courses') }}
                    </flux:button>
                    <flux:error name="courses_to_drop" class="mt-2" />
                </div>
                @endif
            </div>
        </div>
        @endif
    </flux:card>
</div>