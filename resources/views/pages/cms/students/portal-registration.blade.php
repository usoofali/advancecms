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

new #[Layout('layouts.app')] #[Title('My Registrations')] class extends Component {
    public int|string $student_id = '';
    public int|string $session_id = '';
    public $selected_courses = [];
    public int|string $institution_id = '';

    public function mount(): void
    {
        Gate::authorize('registrations.view_personal');

        $user = auth()->user();
        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        // Find the student record associated with the logged in user
        $student = Student::where('email', $user->email)->first();
        if ($student) {
            $this->student_id = $student->id;
            $this->syncCarryovers();
        }
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
            
            // Merge carryovers into existing selection, avoiding duplicates
            $this->selected_courses = collect($this->selected_courses)
                ->merge($carryoverIds)
                ->unique()
                ->toArray();
        }
    }

    public function updatedSessionId(): void
    {
        $this->selected_courses = [];
        $this->syncCarryovers();
    }

    public function register(): void
    {
        Gate::authorize('registrations.view_personal');

        $this->validate([
            'student_id'       => ['required', 'exists:students,id'],
            'session_id'       => ['required', 'exists:academic_sessions,id'],
            'selected_courses' => ['required', 'array', 'min:1'],
        ]);

        // Check if registration is locked for the session
        $isClosed = false;
        if ($this->session_id) {
            $isClosed = RegistrationStatus::where('student_id', $this->student_id)
                ->where('academic_session_id', $this->session_id)
                ->where('status', 'closed')
                ->exists();
        }

        if ($isClosed) {
            $this->addError('selected_courses', 'Registration is closed for this academic session. Please contact your HOD.');
            return;
        }

        // Check payment restriction
        if ($this->session_id) {
            $session = AcademicSession::find($this->session_id);
            $missingInvoices = app(\App\Services\PaymentAccessService::class)->getMissingInvoicesForRegistration(Student::find($this->student_id), $session);
            if ($missingInvoices->isNotEmpty()) {
                $this->addError('selected_courses', 'You must pay all required invoices before registering.');
                return;
            }
        }

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

        // Check credit unit limit
        $registeredUnits = CourseRegistration::where('student_id', $this->student_id)
            ->where('academic_session_id', $this->session_id)
            ->with('course')
            ->get()
            ->sum(fn($r) => $r->course->credit_unit);
            
        $selectedUnits = Course::whereIn('id', $this->selected_courses)->sum('credit_unit');
        $totalUnits = $registeredUnits + $selectedUnits;
        $maxUnits = $student?->program?->department?->max_session_units ?? 24;

        if ($totalUnits > $maxUnits) {
            $this->addError('selected_courses', "Maximum allowed session load is {$maxUnits} units. Your current selection totals {$totalUnits} units.");
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

    public function drop(int|string $registrationId): void
    {
        Gate::authorize('registrations.view_personal');

        // Check if registration is locked for the session
        $isClosed = false;
        if ($this->session_id) {
            $isClosed = RegistrationStatus::where('student_id', $this->student_id)
                ->where('academic_session_id', $this->session_id)
                ->where('status', 'closed')
                ->exists();
        }

        if ($isClosed) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Registration is closed for this academic session.',
            ]);
            return;
        }

        if ($this->session_id) {
            $session = AcademicSession::find($this->session_id);
            $missingInvoices = app(\App\Services\PaymentAccessService::class)->getMissingInvoicesForRegistration(Student::find($this->student_id), $session);
            if ($missingInvoices->isNotEmpty()) {
                 $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'You must pay all required invoices before managing registration.',
                ]);
                return;
            }
        }

        $dropped = CourseRegistration::query()
            ->where('institution_id', $this->institution_id)
            ->where('student_id', $this->student_id)
            ->where('id', $registrationId)
            ->delete();

        if ($dropped > 0) {
            $this->syncCarryovers();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully dropped the course.",
            ]);
        }
    }

    public function render(): \Illuminate\View\View
    {
        $student = $this->student_id ? Student::with('program.department')->find($this->student_id) : null;
        $availableCourses = collect();
        $registeredCourses = collect();
        $carryoverCourses = collect();
        $currentLevel = null;

        // Fetch valid sessions since student's admission year
        $sessions = $student 
            ? AcademicSession::all()
                ->filter(fn($s) => (int) explode('/', $s->name)[0] >= (int) ($student->admission_year ?? 0))
                ->sortByDesc('name')
            : collect();

        $isBlockedByPayment = false;
        $missingInvoices = collect();

        // Check payment restrictions
        if ($student && $this->session_id && $this->session_id !== 'null') {
            $session = AcademicSession::find($this->session_id);
            if ($session) {
                $missingTemplates = app(\App\Services\PaymentAccessService::class)->getMissingInvoicesForRegistration($student, $session);
                if ($missingTemplates->isNotEmpty()) {
                    $isBlockedByPayment = true;
                    $invoiceService = app(\App\Services\StudentInvoiceService::class);
                    foreach ($missingTemplates as $template) {
                        $inv = $invoiceService->materializeInvoice($student, $template);
                        if ($inv) {
                            $missingInvoices->push($inv);
                        }
                    }
                }
            }
        }

        // Detect level and load courses as soon as session is picked
        if ($student && $this->session_id && $this->session_id !== 'null' && !$isBlockedByPayment) {
            $session = AcademicSession::find($this->session_id);
            if ($session) {
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

                    $registeredInSem = CourseRegistration::query()
                        ->where('institution_id', $this->institution_id)
                        ->where('student_id', $this->student_id)
                        ->where('academic_session_id', $this->session_id)
                        ->where('semester_id', $semester->id)
                        ->with('course')
                        ->get();

                    $registeredIds = $registeredInSem->pluck('course_id')->toArray();

                    $semCarryovers = app(GradingService::class)->getCarryoverCourses(
                        $student,
                        (int) $this->institution_id,
                        (int) $this->session_id,
                        (int) $semester->id
                    );

                    $carryoverIds = $semCarryovers->pluck('id');

                    $carryoverCourses = $carryoverCourses->merge($semCarryovers->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
                    
                    $available = $allLevelCourses
                        ->whereNotIn('id', $registeredIds)
                        ->whereNotIn('id', $carryoverIds->all());
                    
                    $availableCourses = $availableCourses->merge($available->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
                    
                    $registeredCourses = $registeredCourses->merge($registeredInSem->map(fn($r) => [
                        'id' => $r->id,
                        'course' => $r->course,
                        'semester_name' => $semName
                    ]));
                }
            }
        }

        return view('pages.cms.students.portal-registration', [
            'sessions'          => $sessions,
            'carryoverCourses'  => $carryoverCourses,
            'availableCourses'  => $availableCourses,
            'registeredCourses' => $registeredCourses,
            'currentLevel'      => $currentLevel,
            'student'           => $student,
            'isClosed'          => ($this->student_id && $this->session_id && $this->session_id !== 'null') 
                                    ? RegistrationStatus::where('student_id', $this->student_id)
                                        ->where('academic_session_id', $this->session_id)
                                        ->where('status', 'closed')
                                        ->exists()
                                    : false,
            'isBlockedByPayment' => $isBlockedByPayment,
            'missingInvoices'    => $missingInvoices,
        ]);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="mb-8 items-center justify-between flex">
        <div>
            <flux:heading size="xl">{{ __('Academic Registration') }}</flux:heading>
            <flux:subheading>{{ __('Manage your course enrollments for the entire academic session') }}</flux:subheading>
        </div>
    </div>

    @if (!$student_id)
    <flux:card>
        <div class="text-center p-8 text-zinc-500">
            <flux:icon.exclamation-triangle class="h-12 w-12 mx-auto mb-4 text-yellow-500" />
            <h3 class="text-lg font-medium text-zinc-900 dark:text-white">{{ __('Student Profile Not Found') }}</h3>
            <p class="mt-1">{{ __('We could not link your account to a student profile. Please contact the academic
                office.') }}</p>
        </div>
    </flux:card>
    @else
    <div class="space-y-6">
        <flux:card class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <flux:select wire:model.live="session_id" :label="__('Academic Session')">
                    <option value="null">{{ __('Select session...') }}</option>
                    @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </flux:select>

                @if ($currentLevel)
                <div class="flex items-center gap-2 pb-2">
                    <span class="text-sm text-zinc-500">{{ __('Current Level:') }}</span>
                    <flux:badge color="blue" size="sm">{{ $currentLevel }} Level</flux:badge>
                </div>
                @endif
            </div>
        </flux:card>

        @if ($session_id && $session_id !== 'null')
        @if ($isClosed)
        <div
            class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 rounded-xl flex items-center gap-3 text-red-700 dark:text-red-400">
            <flux:icon.lock-closed class="size-5" />
            <div class="text-sm font-semibold uppercase tracking-tight">{{ __('Your registration for this session is
                locked and verified.') }}</div>
        </div>
        @endif

        @if ($isBlockedByPayment)
        <flux:card class="p-8 text-center bg-zinc-50 dark:bg-zinc-900 border-2 border-red-200 dark:border-red-900/50">
            <flux:icon.lock-closed class="size-12 mx-auto text-red-500 mb-4" />
            <flux:heading size="xl" class="mb-2">{{ __('Registration Locked') }}</flux:heading>
            <p class="text-zinc-600 dark:text-zinc-400 mb-6 max-w-lg mx-auto">
                {{ __('You have unpaid invoices that are required before you can access academic registration for this session.') }}
            </p>
            <div class="space-y-3 max-w-md mx-auto mb-6 text-left">
                @foreach ($missingInvoices as $inv)
                <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-800 rounded-lg border border-red-100 dark:border-red-900/30">
                    <div class="flex flex-col">
                        <span class="font-bold text-sm">{{ $inv->invoice->category ?? 'Invoice' }}</span>
                        <span class="text-xs text-zinc-500">{{ $inv->invoice->academicSession?->name ?? 'All Sessions' }}</span>
                    </div>
                    <span class="font-mono font-bold text-red-600 dark:text-red-400">
                        ₦{{ number_format($inv->balance, 2) }}
                    </span>
                </div>
                @endforeach
            </div>
            <flux:button variant="primary" href="{{ route('cms.students.portal-invoices') }}" icon="credit-card">
                {{ __('Pay Outstanding Invoices') }}
            </flux:button>
        </flux:card>
        @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left: Registration Columns --}}
            <div class="lg:col-span-2 space-y-8">
                {{-- Mandatory Carryovers --}}
                @if ($carryoverCourses->isNotEmpty())
                <div class="space-y-4">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <flux:icon.exclamation-triangle variant="mini" />
                        <flux:heading size="lg">{{ __('Mandatory Carryover Courses') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($carryoverCourses as $courseData)
                        <label wire:key="carryover-{{ $courseData['id'] }}"
                            class="flex items-center p-4 border rounded-lg bg-red-50/30 dark:bg-red-900/10 border-red-200 dark:border-red-900/30">
                            <div
                                class="flex items-center justify-center w-5 h-5 me-3 rounded border border-red-400 bg-red-500 text-white">
                                <flux:icon.check variant="micro" />
                            </div>
                            <div>
                                <div class="font-mono text-sm font-bold text-red-700 dark:text-red-400 text-uppercase">{{
                                    $courseData['course_code'] }}</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">{{ $courseData['title'] }}</div>
                                <div class="text-[10px] mt-1 font-bold uppercase text-red-500/70 italic">{{
                                    $courseData['semester_name'] }} Semester ({{ $courseData['credit_unit'] }} units)
                                </div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Available Courses grouped by Semester --}}
                @php
                $availableBySemester = $availableCourses->groupBy('semester_name');
                @endphp

                @foreach (['1st', '2nd'] as $semName)
                @php $semCourses = $availableBySemester->get($semName, collect()); @endphp
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                        <flux:heading size="lg">{{ $semName }} Semester Available Courses</flux:heading>
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $semCourses->count() }} Available
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        @forelse ($semCourses as $courseData)
                        <label wire:key="available-{{ $courseData['id'] }}"
                            class="flex items-center p-4 border rounded-xl bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 hover:border-blue-500 dark:hover:border-blue-400 transition-all cursor-pointer group shadow-sm has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/30 dark:has-[:checked]:bg-blue-900/10">
                            <flux:checkbox wire:model.live="selected_courses" :value="$courseData['id']" class="me-3"
                                :disabled="$isClosed" />
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div class="font-mono text-sm font-bold text-zinc-900 dark:text-white uppercase">{{
                                        $courseData['course_code'] }}</div>
                                    <div class="text-xs font-bold text-blue-600 dark:text-blue-400">{{
                                        $courseData['credit_unit'] }} Units</div>
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ $courseData['title'] }}</div>
                            </div>
                        </label>
                        @empty
                        <div
                            class="p-8 text-center border-2 border-dashed rounded-2xl border-zinc-200 dark:border-zinc-800 text-zinc-400 text-sm">
                            {{ __('No additional courses available for this semester.') }}
                        </div>
                        @endforelse
                    </div>
                </div>
                @endforeach

            </div>

            {{-- Right Column: Summary & Registered --}}
            <div class="space-y-6">
                <flux:card class="sticky top-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Session Summary') }}</flux:heading>

                    @php
                    $registeredBySemester = $registeredCourses->groupBy('semester_name');
                    $registeredUnits = $registeredCourses->sum(fn($r) => $r['course']->credit_unit);
                    $selectedCoursesModels = \App\Models\Course::whereIn('id', $this->selected_courses)->get();
                    $selectedUnits = $selectedCoursesModels->sum('credit_unit');
                    $totalSessionUnits = $registeredUnits + $selectedUnits;
                    @endphp

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-zinc-500">{{ __('Already Registered') }}</span>
                            <span class="font-bold font-mono">{{ $registeredUnits }} Units</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-zinc-500">{{ __('Newly Selected') }}</span>
                            <span class="font-bold font-mono text-blue-600 dark:text-blue-400">+ {{ $selectedUnits }} Units</span>
                        </div>
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-2 flex justify-between items-center text-sm">
                            <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ __('Total Session Load') }}</span>
                            @php $maxUnits = $student?->program?->department?->max_session_units ?? 24; @endphp
                            <span class="font-bold font-mono text-lg {{ $totalSessionUnits > $maxUnits ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                                {{ $totalSessionUnits }} / {{ $maxUnits }}
                            </span>
                        </div>
                    </div>

                    @if (!$isClosed)
                    <flux:button variant="primary" class="w-full" icon="plus" wire:click="register"
                        :disabled="count($selected_courses) === 0">
                        {{ __('Register Selected Courses') }}
                    </flux:button>
                    <flux:error name="selected_courses" class="mt-2" />
                    @endif

                    <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-800">
                        <flux:heading size="md" class="mb-4">{{ __('Registered Courses') }}</flux:heading>

                        <div class="space-y-6">
                            @foreach (['1st', '2nd'] as $semName)
                            @php $semReg = $registeredBySemester->get($semName, collect()); @endphp
                            <div class="space-y-2">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-400">{{ $semName }}
                                    Semester</div>
                                <div class="space-y-2">
                                    @forelse ($semReg as $reg)
                                    <div wire:key="registered-{{ $reg['id'] }}"
                                        class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700">
                                        <div class="text-xs font-bold font-mono text-zinc-900 dark:text-white uppercase">
                                            {{ $reg['course']->course_code }}</div>
                                        @if (!$isClosed)
                                        <flux:button variant="ghost" size="sm" icon="trash"
                                            wire:click="drop({{ $reg['id'] }})" />
                                        @endif
                                    </div>
                                    @empty
                                    <p class="text-[10px] text-zinc-400 italic px-1">{{ __('None') }}</p>
                                    @endforelse
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
        @endif
        @else
        <div class="p-12 text-center border-2 border-dashed rounded-2xl border-zinc-200 dark:border-zinc-800">
            <flux:icon.calendar class="mx-auto size-12 text-zinc-300 dark:text-zinc-700 mb-4" />
            <flux:heading size="lg" class="text-zinc-500">{{ __('Select an Academic Session to Begin') }}</flux:heading>
            <flux:subheading>{{ __('Choose the academic year you wish to manage your course registrations for.') }}
            </flux:subheading>
        </div>
        @endif
    </div>
    @endif
</div>