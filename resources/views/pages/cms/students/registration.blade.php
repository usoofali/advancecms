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
    public string $student_search = '';
    public $selected_courses = [];
    public $courses_to_drop = [];
    public int|string $institution_id = '';
    public ?int $department_id = null;

    public function mount(): void
    {
        Gate::authorize('registrations.view');

        $user = auth()->user();

        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        if (!$user->hasRole('Super Admin') && !$user->hasRole('Institutional Admin')) {
            $scopedDeptIds = array_merge(
                $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
                $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class)
            );

            if (!empty($scopedDeptIds)) {
                $this->department_id = $scopedDeptIds[0];
                return;
            }

            // Fallback: Legacy check for HOD via hod_id column
            $staff = \App\Models\Staff::where('email', $user->email)->first();
            if ($staff) {
                $hodDept = \App\Models\Department::where('hod_id', $staff->id)->first();
                if ($hodDept) {
                    $this->department_id = $hodDept->id;
                }
            }
        }
    }

    public function updatedSessionId(): void
    {
        $this->selected_courses = [];
        $this->syncCarryovers();
    }

    public function updatedStudentId(): void
    {
        $this->selected_courses = [];
        $this->syncCarryovers();
    }

    protected function syncCarryovers(): void
    {
        if ($this->student_id && $this->session_id && $this->session_id !== 'null') {
            $student = Student::find($this->student_id);
            $semesters = Semester::where('academic_session_id', $this->session_id)->get();
            
            $allCarryoverIds = [];
            foreach ($semesters as $sem) {
                $carryovers = app(GradingService::class)->getCarryoverCourses(
                    $student,
                    (int) $this->institution_id,
                    (int) $this->session_id,
                    (int) $sem->id
                );
                $allCarryoverIds = array_merge($allCarryoverIds, $carryovers->pluck('id')->toArray());
            }

            $carryoverIds = collect($allCarryoverIds)->unique()->map(fn($id) => (string) $id)->toArray();
            
            $this->selected_courses = collect($this->selected_courses)
                ->merge($carryoverIds)
                ->unique()
                ->toArray();
        }
    }

    public function register(): void
    {
        Gate::authorize('registrations.create');

        $this->validate([
            'student_id'       => ['required', 'exists:students,id'],
            'session_id'       => ['required', 'exists:academic_sessions,id'],
            'selected_courses' => ['required', 'array', 'min:1'],
        ]);

        // Admin view: We don't block registration even if closed, but we keep the status for awareness

        // Verify all mandatory carryover courses are included
        $student = Student::find($this->student_id);
        $semesters = Semester::where('academic_session_id', $this->session_id)->get();
        
        $allCarryovers = collect();
        foreach ($semesters as $sem) {
            $semCarryovers = app(GradingService::class)->getCarryoverCourses(
                $student,
                (int) $this->institution_id,
                (int) $this->session_id,
                (int) $sem->id
            );
            $allCarryovers = $allCarryovers->merge($semCarryovers);
        }
        $selectedIds = collect($this->selected_courses)->map(fn($id) => (int) $id)->toArray();
        $missingCarryovers = $allCarryovers->whereNotIn('id', $selectedIds);

        if ($missingCarryovers->isNotEmpty()) {
            $this->addError('selected_courses', 'You must include all mandatory carryover courses: ' .
                $missingCarryovers->pluck('course_code')->implode(', '));

            return;
        }

        $carryoverIds = $allCarryovers->pluck('id')->toArray();
        $newlyRegistered = 0;
        $alreadyRegistered = 0;

        $courses = Course::whereIn('id', $this->selected_courses)->get();

        foreach ($courses as $course) {
            $targetSem = Semester::where('academic_session_id', $this->session_id)
                ->where('name', $course->semester == 1 ? 'first' : 'second')
                ->first();

            if (!$targetSem) continue;

            $exists = CourseRegistration::query()
                ->where('institution_id', $this->institution_id)
                ->where('student_id', $this->student_id)
                ->where('course_id', $course->id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $targetSem->id)
                ->exists();

            if ($exists) {
                $alreadyRegistered++;
            } else {
                CourseRegistration::create([
                    'institution_id'      => $this->institution_id,
                    'student_id'          => $this->student_id,
                    'course_id'           => $course->id,
                    'academic_session_id' => $this->session_id,
                    'semester_id'         => $targetSem->id,
                    'status'              => 'registered',
                    'is_carryover'        => in_array($course->id, $carryoverIds),
                ]);
                $newlyRegistered++;
            }
        }

        $this->reset(['selected_courses']);
        $this->syncCarryovers();

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
        Gate::authorize('registrations.delete');

        $this->validate([
            'student_id'      => ['required', 'exists:students,id'],
            'session_id'      => ['required', 'exists:academic_sessions,id'],
            'courses_to_drop' => ['required', 'array', 'min:1'],
        ]);

        // Admin view: We don't block dropping even if closed

        $dropped = CourseRegistration::query()
            ->where('institution_id', $this->institution_id)
            ->where('student_id', $this->student_id)
            ->where('academic_session_id', $this->session_id)
            ->whereIn('course_id', $this->courses_to_drop)
            ->delete();

        $this->reset(['courses_to_drop']);
        $this->syncCarryovers();

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

        if ($student && $this->session_id && $this->session_id !== 'null') {
            $session = AcademicSession::find($this->session_id);
            $currentLevel = $student->currentLevel($session);
            $semesters = Semester::where('academic_session_id', $this->session_id)->get();

            foreach ($semesters as $semester) {
                $semName = $semester->name === 'first' ? '1st' : '2nd';
                
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
                    ->where('semester_id', $semester->id)
                    ->pluck('course_id')
                    ->toArray();

                $semCarryovers = app(GradingService::class)->getCarryoverCourses(
                    $student,
                    (int) $this->institution_id,
                    (int) $this->session_id,
                    (int) $semester->id
                );

                $carryoverIds = $semCarryovers->pluck('id');

                $carryoverCourses = $carryoverCourses->merge($semCarryovers->map(function($c) use ($semName) {
                    $arr = $c->toArray();
                    $arr['semester_name'] = $semName;
                    return $arr;
                }));
                
                $available = $allLevelCourses
                    ->whereNotIn('id', $registeredCourseIds)
                    ->whereNotIn('id', $carryoverIds->all());
                
                $availableCourses = $availableCourses->merge($available->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
                
                $allRegisteredInSem = Course::query()
                    ->whereIn('id', $registeredCourseIds)
                    ->get();

                $registeredCourses = $registeredCourses->merge($allRegisteredInSem->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
            }
        }

        return view('pages.cms.students.registration', [
            'institutions' => auth()->user()->institution_id
                ? []
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'students'          => Student::query()
                ->with('program.department')
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->when($this->department_id, function($q) {
                    $q->whereHas('program', fn($pq) => $pq->where('department_id', $this->department_id));
                })
                ->when($this->student_search, function($q) {
                    $q->where(function($sq) {
                        $sq->where('matric_number', 'like', "%{$this->student_search}%")
                           ->orWhere('first_name', 'like', "%{$this->student_search}%")
                           ->orWhere('last_name', 'like', "%{$this->student_search}%");
                    });
                })
                ->orderBy('last_name')
                ->limit(50)
                ->get(),
            'sessions'          => AcademicSession::query()->orderByDesc('name')->get(),
            'semesters'         => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'carryoverCourses'  => $carryoverCourses,
            'availableCourses'  => $availableCourses,
            'registeredCourses' => $registeredCourses,
            'currentLevel'      => $currentLevel,
            'isClosed'          => ($this->student_id && $this->session_id && $this->session_id !== 'null')
                                    ? RegistrationStatus::where('student_id', $this->student_id)
                                        ->where('academic_session_id', $this->session_id)
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

            <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model.live.debounce.300ms="student_search" :label="__('Search Student')" :placeholder="__('Matric number or name...')" icon="magnifying-glass" />

                <flux:select wire:model.live="student_id" :label="__('Select Student')" required :disabled="!$institution_id">
                    <flux:select.option value="null">{{ __('Select student...') }}</flux:select.option>
                    @foreach ($students as $stu)
                    <flux:select.option :value="$stu->id">{{ $stu->full_name }} ({{ $stu->matric_number }})
                    </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model.live="session_id" :label="__('Academic Session')" required>
                <flux:select.option value="null">{{ __('Select session...') }}</flux:select.option>
                @foreach ($sessions as $session)
                <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($isClosed)
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 text-red-700">
            <flux:icon.lock-closed class="size-5" />
            <div class="text-sm font-semibold uppercase tracking-tight">{{ __('Student registration is locked/verified, but you can still make administrative changes.') }}</div>
        </div>
        @endif

        @if ($student_id && $session_id && $session_id !== 'null')
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
                    <label wire:key="carryover-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg bg-red-50/30 dark:bg-red-900/10 border-red-200 dark:border-red-900/30">
                        <div
                            class="flex items-center justify-center w-5 h-5 me-3 rounded border border-red-400 bg-red-500 text-white">
                            <flux:icon.check variant="micro" />
                        </div>
                        <input type="checkbox" wire:model="selected_courses" value="{{ $course['id'] }}" class="hidden"
                            checked disabled />
                        <div>
                            <div class="font-mono text-sm font-bold text-red-700 dark:text-red-400 uppercase">{{
                                $course['course_code'] }}</div>
                            <div class="text-xs text-red-600/80 dark:text-red-500/80">{{ $course['title'] }} ({{
                                $course['credit_unit'] }} units)</div>
                            <div class="text-[10px] mt-1 font-bold uppercase text-red-500/70 italic">{{ $course['semester_name'] }} Semester</div>
                        </div>
                    </label>
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
                    <label wire:key="available-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model.live="selected_courses" :value="$course['id']" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course['course_code'] }}</div>
                            <div class="text-sm">{{ $course['title'] }}</div>
                            <div class="text-xs text-zinc-500">{{ $course['credit_unit'] }} {{ __('Units') }} ({{ $course['semester_name'] }})</div>
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
                    <flux:button variant="primary" wire:click="register">
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
                    @php 
                        $selectedStudent = $students->firstWhere('id', $this->student_id);
                        $maxUnits = $selectedStudent?->program?->department?->max_session_units ?? 24;
                    @endphp
                    <flux:badge color="green" size="sm">
                        {{ __('Total Units:') }} {{ $registeredCourses->sum('credit_unit') }} / {{ $maxUnits }}
                    </flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($registeredCourses as $course)
                    <label wire:key="registered-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model.live="courses_to_drop" :value="$course['id']" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course['course_code'] }}</div>
                            <div class="text-sm">{{ $course['title'] }}</div>
                            <div class="flex gap-2 items-center mt-1">
                                <flux:badge size="sm" color="green">{{ __('Registered') }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $course['credit_unit'] }} {{ __('Units') }} ({{ $course['semester_name'] }})</span>
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
                    <flux:button variant="danger" wire:click="drop">
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