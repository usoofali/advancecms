<?php

use App\Models\CaResult;
use App\Models\StudentCoin;
use App\Models\Course;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Staff CA Leaderboards')] class extends Component {
    use WithPagination;
    public string $tab = 'coins'; // 'coins' or 'academic'

    #[Url]
    public $session_id = '';

    #[Url]
    public $semester_id = '';

    #[Url]
    public $program_id = '';

    #[Url]
    public $level = '';

    #[Url]
    public $course_id = '';

    public function updatedSessionId()
    {
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedSemesterId()
    {
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedProgramId()
    {
        $this->level = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedLevel()
    {
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedCourseId()
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        Gate::authorize('ca_tests.view');
    }

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $sessions = \App\Models\AcademicSession::all();
        $semesters = $this->session_id ? \App\Models\Semester::where('academic_session_id', $this->session_id)->get() : collect();
        $programs = \App\Models\Program::where('institution_id', $user->institution_id)->get();
        $levels = [100, 200, 300, 400, 500, 600];

        $myCourses = Course::when($isRestrictedLecturer, function ($q) use ($user) {
            $q->whereIn('id', function ($sub) use ($user) {
                $sub->select('course_id')
                    ->from('course_allocations')
                    ->where('user_id', $user->id);
            });
        })
            ->when(!empty($scopedDeptIds) && !$isSuperAdmin && !$isInstAdmin, function ($q) use ($scopedDeptIds) {
                $q->whereIn('department_id', $scopedDeptIds);
            })
            ->when($this->program_id, fn($q) => $q->where('program_id', $this->program_id))
            ->when($this->level, fn($q) => $q->where('level', $this->level))
            ->when($this->semester_id, function ($q) use ($semesters) {
                $sem = $semesters->firstWhere('id', $this->semester_id);
                if ($sem) {
                    $val = strtolower($sem->name) === 'first' ? 1 : 2;
                    $q->where('semester', $val);
                }
            })
            ->get();

        $coinLeaders = collect();
        $academicLeaders = collect();

        if ($this->tab === 'coins') {
            $coinLeaders = StudentCoin::whereHas('student.user', function ($q) use ($user) {
                $q->where('institution_id', $user->institution_id);
            })
                ->with('student.user')
                ->orderByDesc('total_coins')
                ->paginate(20);
        } else {
            $academicLeaders = CaResult::selectRaw('student_id, SUM(normalized_score) as total_normalized')
                ->whereHas('caTest', function ($q) use ($user, $isRestrictedLecturer, $scopedDeptIds) {
                    $q->where('institution_id', $user->institution_id);

                    if ($this->session_id) {
                        $q->where('academic_session_id', $this->session_id);
                    }
                    if ($this->semester_id) {
                        $q->where('semester_id', $this->semester_id);
                    }
                    if ($this->course_id) {
                        $q->where('course_id', $this->course_id);
                    } else {
                        if ($this->program_id || $this->level) {
                            $q->whereHas('course', function ($cq) {
                                if ($this->program_id) {
                                    $cq->where('program_id', $this->program_id);
                                }
                                if ($this->level) {
                                    $cq->where('level', $this->level);
                                }
                            });
                        }
                        if ($isRestrictedLecturer) {
                            $q->whereIn('course_id', function ($allocQ) use ($user) {
                                $allocQ->select('course_id')->from('course_allocations')->where('user_id', $user->id);
                            });
                        } elseif (!empty($scopedDeptIds) && !$isSuperAdmin && !$isInstAdmin) {
                            $q->whereHas('course', function ($cq) use ($scopedDeptIds) {
                                $cq->whereIn('department_id', $scopedDeptIds);
                            });
                        }
                    }
                })
                ->with('student.user')
                ->groupBy('student_id')
                ->orderByDesc('total_normalized')
                ->paginate(20);
        }

        return [
            'sessions' => $sessions,
            'semesters' => $semesters,
            'programs' => $programs,
            'levels' => $levels,
            'myCourses' => $myCourses,
            'coinLeaders' => $coinLeaders,
            'academicLeaders' => $academicLeaders,
        ];
    }
}; ?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl">{{ __('Leaderboards') }}</flux:heading>
            <flux:subheading>{{ __('Monitor student performance and CA test rankings.') }}</flux:subheading>
        </div>
        <flux:button :href="route('cms.ca-tests.lecturer.index')" wire:navigate icon="arrow-left" />
    </div>

    <div class="mb-6 flex border-b border-zinc-200 dark:border-zinc-800">
        <button wire:click="$set('tab', 'coins')"
            class="px-4 py-2 font-medium text-sm border-b-2 {{ $tab === 'coins' ? 'border-amber-500 text-amber-600 dark:text-amber-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }} flex items-center gap-2">
            <flux:icon icon="currency-dollar" class="size-4" />
            {{ __('Coins Ranking (Global)') }}
        </button>
        <button wire:click="$set('tab', 'academic')"
            class="px-4 py-2 font-medium text-sm border-b-2 {{ $tab === 'academic' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }} flex items-center gap-2">
            <flux:icon icon="academic-cap" class="size-4" />
            {{ __('Academic Ranking') }}
        </button>
    </div>

    <div>
        @if($tab === 'coins')
            <flux:table :paginate="$coinLeaders">
                <flux:table.columns>
                    <flux:table.column>{{ __('Rank') }}</flux:table.column>
                    <flux:table.column>{{ __('Student') }}</flux:table.column>
                    <flux:table.column>{{ __('Total Coins') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($coinLeaders as $index => $leader)
                        <flux:table.row>
                            <flux:table.cell>
                                @if($index === 0 && $coinLeaders->currentPage() === 1) 🥇
                                @elseif($index === 1 && $coinLeaders->currentPage() === 1) 🥈
                                @elseif($index === 2 && $coinLeaders->currentPage() === 1) 🥉
                                @else <span
                                    class="font-bold text-zinc-400">#{{ ($coinLeaders->currentPage() - 1) * $coinLeaders->perPage() + $index + 1 }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$leader->student->user->name" />
                                    <span class="font-medium">
                                        {{ $leader->student->user->name }}
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-mono text-amber-600 font-bold flex items-center gap-1">
                                    {{ $leader->total_coins }}
                                    <flux:icon icon="currency-dollar" class="size-4" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">
                                {{ __('No coin data yet.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            <div class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4">
                <flux:select wire:model.live="session_id" label="{{ __('Session') }}">
                    <option value="">{{ __('All Time') }}</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="semester_id" label="{{ __('Semester') }}" :disabled="!$session_id">
                    <option value="">{{ __('All Semesters') }}</option>
                    @foreach($semesters as $semester)
                        <option value="{{ $semester->id }}">{{ ucfirst($semester->name) }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="program_id" label="{{ __('Program') }}">
                    <option value="">{{ __('All Programs') }}</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->acronym ?? $program->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="level" label="{{ __('Level') }}">
                    <option value="">{{ __('All Levels') }}</option>
                    @foreach($levels as $lvl)
                        <option value="{{ $lvl }}">{{ $lvl }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="course_id" label="{{ __('Course') }}">
                    <option value="">{{ __('All Courses') }}</option>
                    @foreach($myCourses->sortBy(['semester', 'course_code']) as $course)
                        <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->title }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:table :paginate="$academicLeaders">
                <flux:table.columns>
                    <flux:table.column>{{ __('Rank') }}</flux:table.column>
                    <flux:table.column>{{ __('Student') }}</flux:table.column>
                    <flux:table.column>{{ __('Total CA Score (Normalized)') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($academicLeaders as $index => $leader)
                        <flux:table.row>
                            <flux:table.cell>
                                <span
                                    class="font-bold text-zinc-500">#{{ ($academicLeaders->currentPage() - 1) * $academicLeaders->perPage() + $index + 1 }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$leader->student->user->name" />
                                    <span class="font-medium">
                                        {{ $leader->student->user->name }}
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span
                                    class="font-mono text-emerald-600 font-bold">{{ number_format($leader->total_normalized, 2) }}</span>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">
                                {{ __('No academic data yet for these filters.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>