<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Course Allocations')] class extends Component
{
    public int|string $session_id = '';

    public int|string $semester_id = '';

    public int|string $department_id = '';

    public int|string $program_id = '';

    public int|string $course_id = '';

    public int|string $user_id = '';

    public int|string $institution_id = '';

    public int|string|null $revokingId = null;

    public bool $isHod = false;
    public array $hodDepartmentIds = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        $staff = Staff::where('email', $user->email)->first();
        if ($staff) {
            $this->hodDepartmentIds = Department::where('hod_id', $staff->id)->pluck('id')->toArray();
            if (!empty($this->hodDepartmentIds)) {
                $this->isHod = true;
                if (count($this->hodDepartmentIds) === 1) {
                    $this->department_id = $this->hodDepartmentIds[0];
                }
            }
        }
    }

    public function updatedInstitutionId(): void
    {
        $this->department_id = 'null';
        $this->program_id = 'null';
        $this->course_id = 'null';
    }

    public function updatedSessionId(): void
    {
        $this->semester_id = 'null';
        $this->department_id = 'null';
        $this->program_id = 'null';
        $this->course_id = 'null';
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = 'null';
        $this->course_id = 'null';
    }

    public function updatedProgramId(): void
    {
        $this->course_id = 'null';
    }

    public function allocate(): void
    {
        $this->validate([
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        // Check if allocation already exists
        $exists = CourseAllocation::where([
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'academic_session_id' => $this->session_id,
            'semester_id' => $this->semester_id,
        ])->exists();

        if ($exists) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'This course is already allocated to this lecturer for the selected session and semester.',
            ]);
            $this->dispatch('allocation-failed');

            return;
        }

        // If institution_id is empty (e.g., super admin didn't select one or mounting didn't set it),
        // fallback to the institution of the selected course.
        $instId = $this->institution_id ?: Course::find($this->course_id)->institution_id;

        CourseAllocation::create([
            'institution_id' => $instId,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'academic_session_id' => $this->session_id,
            'semester_id' => $this->semester_id,
        ]);

        $this->reset(['course_id', 'user_id']);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Course allocated successfully!',
        ]);
        $this->dispatch('allocation-created');
    }

    public function confirmRevoke(): void
    {
        if (! $this->revokingId) {
            return;
        }

        CourseAllocation::query()
            ->when($this->institution_id && $this->institution_id !== 'null', fn ($q) => $q->where('institution_id', $this->institution_id))
            ->where('id', $this->revokingId)
            ->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Course allocation revoked.',
        ]);
        $this->dispatch('allocation-revoked');

        $this->revokingId = null;
        $this->dispatch('modal-close', name: 'revoke-allocation');
    }

    public function render(): View
    {
        $staffQuery = User::whereHas('roles', function ($q) {
            $q->whereIn('role_name', ['Lecturer', 'HOD']);
        });

        if ($this->institution_id) {
            $staffQuery->where('institution_id', $this->institution_id);
        }

        $coursesQuery = Course::query();
        if ($this->institution_id && $this->institution_id !== 'null') {
            $coursesQuery->where('institution_id', $this->institution_id);
        }
        if ($this->isHod && (!$this->department_id || $this->department_id === 'null')) {
            $coursesQuery->whereIn('department_id', $this->hodDepartmentIds);
        }
        if ($this->department_id && $this->department_id !== 'null') {
            $coursesQuery->where('department_id', $this->department_id);
        }
        if ($this->program_id && $this->program_id !== 'null') {
            $coursesQuery->where('program_id', $this->program_id);
        }

        $allocationsQuery = CourseAllocation::with(['user', 'course.department', 'course.program', 'academicSession', 'semester'])
            ->latest('course_allocations.created_at');

        if ($this->isHod && (!$this->department_id || $this->department_id === 'null')) {
            $allocationsQuery->whereHas('course', fn($q) => $q->whereIn('department_id', $this->hodDepartmentIds));
        }

        if ($this->institution_id && $this->institution_id !== 'null') {
            $allocationsQuery->where('course_allocations.institution_id', $this->institution_id);
        }

        if (($this->department_id && $this->department_id !== 'null') || ($this->program_id && $this->program_id !== 'null')) {
            $allocationsQuery->join('courses', 'course_allocations.course_id', '=', 'courses.id')
                ->select('course_allocations.*');

            if ($this->department_id && $this->department_id !== 'null') {
                $allocationsQuery->where('courses.department_id', $this->department_id);
            }

            if ($this->program_id && $this->program_id !== 'null') {
                $allocationsQuery->where('courses.program_id', $this->program_id);
            }
        }

        if ($this->session_id && $this->session_id !== 'null') {
            $allocationsQuery->where('course_allocations.academic_session_id', $this->session_id);
        }

        if ($this->semester_id && $this->semester_id !== 'null') {
            $allocationsQuery->where('course_allocations.semester_id', $this->semester_id);
        }

        $departmentsQuery = Department::query();
        if ($this->institution_id && $this->institution_id !== 'null') {
            $departmentsQuery->where('institution_id', $this->institution_id);
        }
        if ($this->isHod) {
            $departmentsQuery->whereIn('id', $this->hodDepartmentIds);
        }

        $programsQuery = Program::query();
        if ($this->institution_id && $this->institution_id !== 'null') {
            $programsQuery->where('institution_id', $this->institution_id);
        }
        if ($this->isHod && (!$this->department_id || $this->department_id === 'null')) {
            $programsQuery->whereIn('department_id', $this->hodDepartmentIds);
        }
        if ($this->department_id && $this->department_id !== 'null') {
            $programsQuery->where('department_id', $this->department_id);
        }

        return view('pages.cms.courses.allocations', [
            'institutions' => auth()->user()->institution_id
                ? []
                : Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'sessions' => AcademicSession::where('status', 'active')->get(),
            'semesters' => ($this->session_id && $this->session_id !== 'null') ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'departments' => $departmentsQuery->orderBy('name')->get(),
            'programs' => $programsQuery->orderBy('name')->get(),
            'courses' => $coursesQuery->orderBy('course_code')->get(),
            'staff' => $staffQuery->orderBy('name')->get(),
            'allocations' => $allocationsQuery->get(),
        ]);
    }
}; ?>

<div class="mx-auto max-w-6xl print:max-w-none print:mx-0">
    <div class="mb-8 items-center justify-between flex print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Course Allocations') }}</flux:heading>
            <flux:subheading>{{ __('Assign courses to lecturers for specific sessions and semesters') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <x-action-message on="allocation-created">
                <flux:badge color="green">{{ __('Course allocated successfully!') }}</flux:badge>
            </x-action-message>

            <x-action-message on="allocation-revoked">
                <flux:badge color="zinc">{{ __('Allocation revoked.') }}</flux:badge>
            </x-action-message>

            <x-action-message on="allocation-failed">
                <flux:badge color="red">{{ __('Allocation failed (already exists).') }}</flux:badge>
            </x-action-message>
        </div>

        <flux:button variant="ghost" icon="printer" class="hidden md:flex print:hidden" onclick="window.print()">
            {{ __('Print Allocations') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 print:block">
        <!-- Allocation Form -->
        <div class="md:col-span-1 print:hidden">
            <flux:card>
                <form wire:submit="allocate" class="space-y-6">
                    @if (!auth()->user()->institution_id)
                    <flux:select wire:model.live="institution_id" :label="__('Institution')">
                        <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                        @foreach ($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @endif

                    <flux:select wire:model.live="session_id" :label="__('Academic Session')" required>
                        <flux:select.option value="null">{{ __('Select session...') }}</flux:select.option>
                        @foreach ($sessions as $session)
                        <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="semester_id" :label="__('Semester')" required
                        :disabled="!$session_id || $session_id === 'null'">
                        <flux:select.option value="null">{{ __('Select semester...') }}</flux:select.option>
                        @foreach ($semesters as $semester)
                        <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if (!$this->isHod || count($this->hodDepartmentIds) > 1)
                    <flux:select wire:model.live="department_id" :label="__('Department')">
                        <flux:select.option value="null">{{ $this->isHod ? __('All My Departments') : __('All Departments (Optional)...') }}</flux:select.option>
                        @foreach ($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @endif

                    <flux:select wire:model.live="program_id" :label="__('Program')"
                        :disabled="!$department_id || $department_id === 'null'">
                        <flux:select.option value="null">
                            @if(!$department_id || $department_id === 'null')
                            {{ __('Select department first...') }}
                            @else
                            {{ __('All Programs (Optional)...') }}
                            @endif
                        </flux:select.option>
                        @foreach ($programs as $program)
                        <flux:select.option :value="$program->id">{{ $program->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="course_id" :label="__('Course')" required
                        :disabled="!$program_id || $program_id === 'null'">
                        <flux:select.option value="null">
                            @if(!$program_id || $program_id === 'null')
                            {{ __('Select program first...') }}
                            @else
                            {{ __('Select course...') }}
                            @endif
                        </flux:select.option>
                        @foreach ($courses as $course)
                        <flux:select.option :value="$course->id">{{ $course->course_code }} - {{ $course->title }}
                        </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="user_id" :label="__('Lecturer')" required>
                        <flux:select.option value="null">{{ __('Select lecturer...') }}</flux:select.option>
                        @foreach ($staff as $user)
                        <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="flex justify-end pt-2">
                        <flux:button variant="primary" type="submit">
                            {{ __('Allocate Course') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>

        <!-- Allocations List -->
        <div class="md:col-span-2 print:col-span-1 print:w-full">
            <flux:card
                class="print:!border-none print:!shadow-none print:!rounded-none print:!p-0 print:!bg-transparent text-black dark:print:text-black">
                <div class="hidden print:block mb-8 text-center text-black dark:text-black">
                    <h2 class="text-2xl font-bold uppercase tracking-wider">{{ auth()->user()->institution->name ??
                        __('Course Allocations') }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-600 uppercase font-medium">
                        {{ __('Allocated Courses') }}
                        @if($this->session_id && $this->session_id !== 'null')
                        - {{ \App\Models\AcademicSession::find($this->session_id)?->name }}
                        @endif
                        @if($this->semester_id && $this->semester_id !== 'null')
                        - {{ ucfirst(\App\Models\Semester::find($this->semester_id)?->name) }}
                        @endif
                        @if($this->department_id && $this->department_id !== 'null')
                        - {{ \App\Models\Department::find($this->department_id)?->name }}
                        @endif
                        @if($this->program_id && $this->program_id !== 'null')
                        - {{ \App\Models\Program::find($this->program_id)?->name }}
                        @endif
                    </p>
                </div>

                <flux:heading size="md" class="mb-4 print:hidden">{{ __('Current Allocations') }}</flux:heading>

                <div class="overflow-x-auto print:overflow-visible">
                    <table class="w-full text-left text-sm border-collapse print:text-black dark:print:text-black">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 print:border-black/20">
                                <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white print:text-black">{{
                                    __('Lecturer') }}</th>
                                <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white print:text-black">{{
                                    __('Course / Dept / Program') }}</th>
                                <th class="px-4 py-3 font-semibold text-zinc-900 dark:text-white print:text-black">{{
                                    __('Session / Semester') }}</th>
                                <th
                                    class="px-4 py-3 font-semibold text-right text-zinc-900 dark:text-white print:hidden">
                                    {{ __('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 print:divide-black/20">
                            @forelse ($allocations as $alloc)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 print:hover:bg-transparent">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-zinc-900 dark:text-white print:text-black">{{
                                        $alloc->user->name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="font-mono text-sm uppercase text-black dark:text-white print:text-black">
                                        {{ $alloc->course->course_code }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 print:text-black/70">{{
                                        Str::limit($alloc->course->title, 40) }}</div>
                                    <div class="mt-1 flex gap-1 flex-wrap text-[10px] uppercase font-bold text-zinc-400 dark:text-zinc-500 print:text-black/50">
                                        <span>{{ $alloc->course->department?->name }}</span>
                                        <span class="text-zinc-300 dark:text-zinc-700 mx-0.5">•</span>
                                        <span>{{ $alloc->course->program?->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-black dark:text-white print:text-black">{{
                                        $alloc->academicSession->name }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 print:text-black/70">{{
                                        ucfirst($alloc->semester->name) }}</div>
                                </td>
                                <td class="px-4 py-3 text-right print:hidden">
                                    <flux:button size="sm" variant="danger" icon="trash" title="{{ __('Revoke') }}"
                                        x-on:click="$wire.revokingId = {{ $alloc->id }}; $flux.modal('revoke-allocation').show()">
                                    </flux:button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4"
                                    class="text-center text-zinc-500 dark:text-zinc-400 py-8 print:text-black/60">
                                    {{ __('No course allocations found.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </div>
    </div>

    <flux:modal name="revoke-allocation" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmRevoke" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Revoke Course Allocation?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to revoke this course allocation? The lecturer will no longer be able
                    to enter results for this course.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Revoke') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>