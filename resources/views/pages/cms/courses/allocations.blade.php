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
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Course Allocations')] class extends Component {
    use WithPagination;

    public string $search = '';

    public int|string $session_id = '';

    public int|string $semester_id = '';

    public int|string $department_id = '';

    public int|string $program_id = '';

    public int|string $level = 'null';

    public int|string $course_id = '';

    public int|string $user_id = '';

    public int|string $institution_id = '';

    public int|string|null $revokingId = null;

    public bool $isHod = false;
    public array $hodDepartmentIds = [];

    public function mount(): void
    {
        Gate::authorize('courses.allocate');

        $user = auth()->user();
        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        if (!$user->hasRole('Super Admin') && !$user->hasRole('Institutional Admin')) {
            $scopedDeptIds = array_merge(
                $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
                $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
                $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
            );

            if (!empty($scopedDeptIds)) {
                $this->isHod = true;
                $this->hodDepartmentIds = $scopedDeptIds;
            }

            $staff = Staff::where('email', $user->email)->first();
            if ($staff) {
                $legacyHodDeptIds = Department::where('hod_id', $staff->id)->pluck('id')->toArray();
                if (!empty($legacyHodDeptIds)) {
                    $this->isHod = true;
                    $this->hodDepartmentIds = array_unique(array_merge($this->hodDepartmentIds, $legacyHodDeptIds));
                }
            }

            if ($this->isHod && count($this->hodDepartmentIds) === 1) {
                $this->department_id = $this->hodDepartmentIds[0];
            }
        }
    }

    protected function resetDepartmentId(): void
    {
        if ($this->isHod && count($this->hodDepartmentIds) === 1) {
            $this->department_id = $this->hodDepartmentIds[0];
        } else {
            $this->department_id = 'null';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedInstitutionId(): void
    {
        $this->resetDepartmentId();
        $this->program_id = 'null';
        $this->level = 'null';
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function updatedSessionId(): void
    {
        $this->semester_id = 'null';
        $this->resetDepartmentId();
        $this->program_id = 'null';
        $this->level = 'null';
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = 'null';
        $this->level = 'null';
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function updatedProgramId(): void
    {
        $this->level = 'null';
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function updatedLevel(): void
    {
        $this->course_id = 'null';
        $this->resetPage();
    }

    public function allocate(): void
    {
        Gate::authorize('courses.allocate');

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
        Gate::authorize('courses.revoke_allocation');

        if (!$this->revokingId) {
            return;
        }

        CourseAllocation::query()
            ->when($this->institution_id && $this->institution_id !== 'null', fn($q) => $q->where('institution_id', $this->institution_id))
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
        if ($this->level && $this->level !== 'null') {
            $coursesQuery->where('level', $this->level);
        }
        if ($this->semester_id && $this->semester_id !== 'null') {
            $selectedSemesterObj = Semester::find($this->semester_id);
            if ($selectedSemesterObj) {
                $semesterName = strtolower(trim($selectedSemesterObj->name));
                if ($semesterName === 'first' || str_contains($semesterName, 'first') || str_contains($semesterName, '1st') || $semesterName === '1') {
                    $coursesQuery->where('semester', 1);
                } elseif ($semesterName === 'second' || str_contains($semesterName, 'second') || str_contains($semesterName, '2nd') || $semesterName === '2') {
                    $coursesQuery->where('semester', 2);
                }
            }
        }

        $allocationsQuery = CourseAllocation::with(['user', 'course.department', 'course.program', 'academicSession', 'semester'])
            ->latest('course_allocations.created_at');

        if ($this->isHod && (!$this->department_id || $this->department_id === 'null')) {
            $allocationsQuery->whereHas('course', fn($q) => $q->whereIn('department_id', $this->hodDepartmentIds));
        }

        if ($this->institution_id && $this->institution_id !== 'null') {
            $allocationsQuery->where('course_allocations.institution_id', $this->institution_id);
        }

        if (($this->department_id && $this->department_id !== 'null') || ($this->program_id && $this->program_id !== 'null') || ($this->level && $this->level !== 'null')) {
            $allocationsQuery->join('courses', 'course_allocations.course_id', '=', 'courses.id')
                ->select('course_allocations.*');

            if ($this->department_id && $this->department_id !== 'null') {
                $allocationsQuery->where('courses.department_id', $this->department_id);
            }

            if ($this->program_id && $this->program_id !== 'null') {
                $allocationsQuery->where('courses.program_id', $this->program_id);
            }

            if ($this->level && $this->level !== 'null') {
                $allocationsQuery->where('courses.level', $this->level);
            }
        }

        if ($this->session_id && $this->session_id !== 'null') {
            $allocationsQuery->where('course_allocations.academic_session_id', $this->session_id);
        }

        if ($this->semester_id && $this->semester_id !== 'null') {
            $allocationsQuery->where('course_allocations.semester_id', $this->semester_id);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $allocationsQuery->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('course', fn ($cq) => $cq->where('course_code', 'like', $term)->orWhere('title', 'like', $term));
            });
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
            'sessions' => AcademicSession::orderBy('name')->get(),
            'semesters' => ($this->session_id && $this->session_id !== 'null') ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'departments' => $departmentsQuery->orderBy('name')->get(),
            'programs' => $programsQuery->orderBy('name')->get(),
            'courses' => $coursesQuery->orderBy('course_code')->get(),
            'staff' => $staffQuery->orderBy('name')->get(),
            'allocations' => $allocationsQuery->paginate(15),
        ]);
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 print:max-w-none print:mx-0">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div>
            <flux:heading size="xl">{{ __('Course Allocations') }}</flux:heading>
            <flux:subheading>{{ __('Assign courses to lecturers by academic session and semester') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-action-message on="allocation-created">
                <flux:badge color="green">{{ __('Course allocated!') }}</flux:badge>
            </x-action-message>

            <x-action-message on="allocation-revoked">
                <flux:badge color="zinc">{{ __('Allocation revoked.') }}</flux:badge>
            </x-action-message>

            <x-action-message on="allocation-failed">
                <flux:badge color="red">{{ __('Already allocated.') }}</flux:badge>
            </x-action-message>

            <flux:button variant="filled" icon="printer" class="w-full sm:w-auto"
                x-on:click="window.open('{{ route('cms.courses.allocations.print') }}?session_id=' + $wire.session_id + '&semester_id=' + $wire.semester_id + '&department_id=' + $wire.department_id + '&program_id=' + $wire.program_id + '&level=' + $wire.level + '&institution_id=' + $wire.institution_id, '_blank')">
                {{ __('Print Report') }}
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 print:block">
        {{-- Allocation Form --}}
        <div class="lg:col-span-4 print:hidden">
            @can('courses.allocate')
                <flux:card class="space-y-5">
                    <div class="border-b border-zinc-100 pb-3 dark:border-zinc-800">
                        <flux:heading size="lg">{{ __('Assign Lecturer') }}</flux:heading>
                        <flux:subheading>{{ __('Filter a course and select a lecturer') }}</flux:subheading>
                    </div>

                    <form wire:submit="allocate" class="space-y-4">
                        @if (!auth()->user()->institution_id)
                            <flux:select wire:model.live="institution_id" :label="__('Institution')">
                                <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                                @foreach ($institutions as $inst)
                                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <flux:select wire:model.live="session_id" :label="__('Session')" required>
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
                        </div>

                        @if (!$this->isHod || count($this->hodDepartmentIds) > 1)
                            <flux:select wire:model.live="department_id" :label="__('Department')">
                                <flux:select.option value="null">
                                    {{ $this->isHod ? __('All My Departments') : __('All Departments') }}
                                </flux:select.option>
                                @foreach ($departments as $dept)
                                    <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <flux:select wire:model.live="program_id" :label="__('Program')"
                                :disabled="!$department_id || $department_id === 'null'">
                                <flux:select.option value="null">
                                    {{ (!$department_id || $department_id === 'null') ? __('Select dept. first') : __('All Programs') }}
                                </flux:select.option>
                                @foreach ($programs as $program)
                                    <flux:select.option :value="$program->id">{{ $program->acronym ?? $program->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="level" :label="__('Level')"
                                :disabled="!$program_id || $program_id === 'null'">
                                <flux:select.option value="null">
                                    {{ (!$program_id || $program_id === 'null') ? __('Select prog. first') : __('All Levels') }}
                                </flux:select.option>
                                @foreach ([100, 200, 300, 400, 500] as $lvl)
                                    <flux:select.option value="{{ $lvl }}">{{ $lvl }}L</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <flux:select wire:model="course_id" :label="__('Course')" required
                            :disabled="!$program_id || $program_id === 'null'">
                            <flux:select.option value="null">
                                {{ (!$program_id || $program_id === 'null') ? __('Select program first...') : __('Select course...') }}
                            </flux:select.option>
                            @foreach ($courses as $course)
                                <flux:select.option :value="$course->id">
                                    {{ $course->course_code }} — {{ Str::limit($course->title, 35) }} ({{ $course->level }}L)
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="user_id" :label="__('Lecturer')" required>
                            <flux:select.option value="null">{{ __('Select lecturer...') }}</flux:select.option>
                            @foreach ($staff as $user)
                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="pt-2">
                            <flux:button variant="primary" type="submit" icon="plus" class="w-full">
                                {{ __('Allocate Course') }}
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            @else
                <div class="rounded-2xl border-2 border-dashed border-zinc-200 p-6 text-center text-zinc-400 dark:border-zinc-800">
                    <flux:icon.shield-exclamation class="mx-auto mb-3 size-10 opacity-30" />
                    <p class="text-sm">{{ __('View existing course allocations in the table.') }}</p>
                </div>
            @endcan
        </div>

        {{-- Allocations Table --}}
        <div class="lg:col-span-8 print:col-span-1 print:w-full">
            <flux:card class="space-y-4 print:!border-none print:!shadow-none print:!rounded-none print:!p-0 print:!bg-transparent">
                <div class="hidden print:block mb-6 text-center text-black">
                    <h2 class="text-2xl font-bold uppercase tracking-wider">
                        {{ auth()->user()->institution->name ?? __('Course Allocations') }}
                    </h2>
                    <p class="mt-1 text-sm font-medium uppercase text-zinc-600">
                        {{ __('Allocated Courses') }}
                        @if($this->session_id && $this->session_id !== 'null')
                            - {{ \App\Models\AcademicSession::find($this->session_id)?->name }}
                        @endif
                        @if($this->semester_id && $this->semester_id !== 'null')
                            - {{ ucfirst(\App\Models\Semester::find($this->semester_id)?->name) }}
                        @endif
                        @if($this->program_id && $this->program_id !== 'null')
                            - {{ \App\Models\Program::find($this->program_id)?->acronym ?? \App\Models\Program::find($this->program_id)?->name }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Current Allocations') }}</flux:heading>
                        <flux:badge size="sm" color="indigo">{{ $allocations->total() }}</flux:badge>
                    </div>

                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        size="sm"
                        placeholder="{{ __('Search lecturer or course...') }}"
                        class="w-full sm:w-64"
                    />
                </div>

                <flux:table :paginate="$allocations">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Lecturer') }}</flux:table.column>
                        <flux:table.column>{{ __('Course / Program') }}</flux:table.column>
                        <flux:table.column>{{ __('Session / Semester') }}</flux:table.column>
                        <flux:table.column class="text-right print:hidden">{{ __('Action') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($allocations as $alloc)
                            <flux:table.row wire:key="alloc-{{ $alloc->id }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $alloc->user?->name ?? '—' }}
                                    </div>
                                    @if ($alloc->user?->email)
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $alloc->user->email }}</div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono text-xs font-bold uppercase text-zinc-900 dark:text-zinc-100">
                                            {{ $alloc->course?->course_code }}
                                        </span>
                                        @if ($alloc->course?->program)
                                            <flux:badge size="xs" color="indigo">
                                                {{ $alloc->course->program->acronym ?: $alloc->course->program->name }}
                                            </flux:badge>
                                        @endif
                                        @if ($alloc->course?->level)
                                            <flux:badge size="xs" color="zinc">{{ $alloc->course->level }}L</flux:badge>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ Str::limit($alloc->course?->title ?? '', 45) }}
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $alloc->academicSession?->name ?? '—' }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $alloc->semester ? ucfirst($alloc->semester->name) : '—' }}
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="text-right print:hidden">
                                    @can('courses.revoke_allocation')
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            class="text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                            title="{{ __('Revoke Allocation') }}"
                                            x-on:click="$wire.revokingId = {{ $alloc->id }}; $flux.modal('revoke-allocation').show()"
                                        />
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No course allocations found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    </div>

    <flux:modal name="revoke-allocation" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmRevoke" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Revoke Course Allocation?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to revoke this course allocation? The lecturer will no longer be able to enter results for this course.') }}
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