<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\CourseRegistration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Course Details')] class extends Component {
    use WithPagination;

    public Course $course;

    public int|string $selectedSessionId = '';

    public string $search = '';

    public function mount(Course $course): void
    {
        $user = auth()->user();

        if ($user->cannot('courses.view') && $user->cannot('courses.view_dept')) {
            abort(403, 'Unauthorized action.');
        }

        $this->course = $course->load(['department.institution', 'program', 'institution']);

        $active = AcademicSession::where('status', 'active')->first();
        if ($active) {
            $this->selectedSessionId = $active->id;
        }
    }

    public function updatedSelectedSessionId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $sessions = AcademicSession::orderByDesc('id')->get();

        $allocations = CourseAllocation::with(['user', 'academicSession', 'semester'])
            ->where('course_id', $this->course->id)
            ->when($this->selectedSessionId, fn($q) => $q->where('academic_session_id', $this->selectedSessionId))
            ->latest()
            ->get();

        $registrationsQuery = CourseRegistration::with(['student', 'academicSession', 'semester'])
            ->where('course_id', $this->course->id)
            ->select('course_registrations.*')
            ->leftJoin('students', 'course_registrations.student_id', '=', 'students.id')
            ->when($this->selectedSessionId, fn($q) => $q->where('academic_session_id', $this->selectedSessionId))
            ->when($this->search, function ($q) {
                $q->where(fn($sq) => $sq
                    ->where('students.first_name', 'like', "%{$this->search}%")
                    ->orWhere('students.last_name', 'like', "%{$this->search}%")
                    ->orWhere('students.matric_number', 'like', "%{$this->search}%"));
            })
            ->orderBy('students.matric_number');

        $totalRegistrations = (clone $registrationsQuery)->count();
        $carryovers = (clone $registrationsQuery)->where('is_carryover', true)->count();

        return [
            'sessions' => $sessions,
            'allocations' => $allocations,
            'registrations' => $registrationsQuery->paginate(20),
            'totalRegistrations' => $totalRegistrations,
            'carryovers' => $carryovers,
            'selectedSession' => $this->selectedSessionId ? $sessions->find($this->selectedSessionId) : null,
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <span class="font-mono text-sm font-black uppercase tracking-wider text-zinc-500 bg-zinc-100 dark:bg-zinc-900 px-2.5 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    {{ $course->course_code }}
                </span>
                <flux:badge color="{{ $course->status === 'active' ? 'green' : 'zinc' }}" inset="top bottom" size="sm" class="font-bold">
                    {{ ucfirst($course->status ?? 'Active') }}
                </flux:badge>
                <flux:badge color="{{ $course->course_type === 'core' ? 'orange' : 'blue' }}" inset="top bottom" size="sm">
                    {{ ucfirst($course->course_type ?? 'Core') }}
                </flux:badge>
                <flux:badge color="indigo" inset="top bottom" size="sm">
                    {{ $course->level }}L &bull; {{ $course->semester == 1 ? '1st Semester' : '2nd Semester' }}
                </flux:badge>
                <flux:badge color="sky" inset="top bottom" size="sm">
                    {{ $course->credit_unit }} {{ Str::plural('Unit', $course->credit_unit) }}
                </flux:badge>
            </div>

            <flux:heading size="xl" class="font-black leading-tight">{{ $course->title }}</flux:heading>

            <p class="text-sm text-zinc-500 font-medium">
                {{ $course->department->name }}
                @if($course->program)
                    &bull; {{ $course->program->name }}
                @endif
                &bull; {{ $course->department->institution->acronym }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <flux:button icon="arrow-left" variant="ghost" :href="route('cms.courses.index')" wire:navigate>
                {{ __('Back to Courses') }}
            </flux:button>
            @can('courses.edit')
                <flux:button icon="pencil" variant="primary" :href="route('cms.courses.edit', $course)" wire:navigate>
                    {{ __('Edit Course') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="relative overflow-hidden group border-none bg-blue-600">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.user-group class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-blue-200 uppercase tracking-widest">{{ __('Registered Students') }}</div>
                <div class="text-4xl font-black text-white">{{ $totalRegistrations }}</div>
                <div class="text-[10px] text-blue-100 font-bold uppercase">
                    @if($selectedSession)
                        {{ $selectedSession->name }}
                    @else
                        {{ __('All Sessions') }}
                    @endif
                </div>
            </div>
        </flux:card>

        <flux:card class="relative overflow-hidden group border-none bg-amber-500">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.arrow-path class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-amber-100 uppercase tracking-widest">{{ __('Carry-Over Students') }}</div>
                <div class="text-4xl font-black text-white">{{ $carryovers }}</div>
                <div class="text-[10px] text-amber-50 font-bold uppercase">{{ __('Repeating this course') }}</div>
            </div>
        </flux:card>

        <flux:card class="relative overflow-hidden group border-none bg-emerald-600">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.academic-cap class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-emerald-100 uppercase tracking-widest">{{ __('Allocated Lecturers') }}</div>
                <div class="text-4xl font-black text-white">{{ $allocations->count() }}</div>
                <div class="text-[10px] text-emerald-50 font-bold uppercase">
                    @if($selectedSession)
                        {{ $selectedSession->name }}
                    @else
                        {{ __('All Sessions') }}
                    @endif
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Session Filter --}}
    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
        <div class="sm:w-72">
            <flux:select wire:model.live="selectedSessionId" :label="__('Academic Session')">
                <option value="">{{ __('All Sessions') }}</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Allocated Lecturers --}}
    <flux:card class="space-y-4">
        <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <flux:icon.academic-cap class="size-5 text-zinc-400" />
            <flux:heading size="lg">{{ __('Allocated Lecturers') }}</flux:heading>
            <flux:badge color="zinc" size="sm" inset="top bottom">{{ $allocations->count() }}</flux:badge>
        </div>

        @if($allocations->isEmpty())
            <div class="py-10 text-center">
                <flux:icon.user-minus class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">{{ __('No lecturer has been allocated to this course') }}
                    @if($selectedSession) {{ __('for') }} {{ $selectedSession->name }} @endif.
                </p>
                @can('courses.view')
                    <flux:button variant="ghost" size="sm" icon="plus" :href="route('cms.courses.allocations')" wire:navigate class="mt-3">
                        {{ __('Allocate a Lecturer') }}
                    </flux:button>
                @endcan
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($allocations as $allocation)
                    <div class="flex items-start gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-100 dark:border-zinc-800">
                        <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                            <flux:icon.user class="size-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-zinc-900 dark:text-white text-sm truncate">{{ $allocation->user->name }}</p>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ $allocation->academicSession->name }}</p>
                            <p class="text-xs text-zinc-400 mt-0.5 capitalize">{{ ucfirst($allocation->semester->name) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- Registered Students --}}
    <flux:card class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div class="flex items-center gap-3">
                <flux:icon.user-group class="size-5 text-zinc-400" />
                <flux:heading size="lg">{{ __('Registered Students') }}</flux:heading>
                <flux:badge color="zinc" size="sm" inset="top bottom">{{ $totalRegistrations }}</flux:badge>
            </div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name or matric no...')"
                class="sm:w-72"
                clearable
            />
        </div>

        @php $paginated = $registrations; @endphp

        <flux:table :paginate="$paginated">
            <flux:table.columns>
                <flux:table.column>{{ __('Matric No.') }}</flux:table.column>
                <flux:table.column>{{ __('Student Name') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Session') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Semester') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($paginated as $reg)
                    <flux:table.row :wire:key="'reg-'.$reg->id">
                        <flux:table.cell>
                            <span class="font-mono text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">
                                {{ $reg->student->matric_number }}
                            </span>
                        </flux:table.cell>

                        <flux:table.cell>
                            @can('students.view_dept')
                                <a href="{{ route('cms.students.show', $reg->student) }}"
                                   class="font-semibold text-zinc-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                   wire:navigate>
                                    {{ $reg->student->full_name }}
                                </a>
                            @else
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $reg->student->full_name }}</span>
                            @endcan
                        </flux:table.cell>

                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">
                            {{ $reg->academicSession->name }}
                        </flux:table.cell>

                        <flux:table.cell class="hidden md:table-cell text-zinc-500 capitalize">
                            {{ ucfirst($reg->semester->name) }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($reg->is_carryover)
                                <flux:badge color="amber" size="sm" inset="top bottom">{{ __('Carryover') }}</flux:badge>
                            @else
                                <flux:badge color="sky" size="sm" inset="top bottom">{{ __('Normal') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                color="{{ $reg->status === 'approved' ? 'green' : ($reg->status === 'pending' ? 'amber' : 'red') }}"
                                size="sm"
                                inset="top bottom"
                            >
                                {{ ucfirst($reg->status ?? 'registered') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center text-zinc-500">
                            {{ $search ? __('No students match your search.') : __('No students registered for this course') }}
                            @if($selectedSession && !$search) {{ __('in') }} {{ $selectedSession->name }} @endif.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
