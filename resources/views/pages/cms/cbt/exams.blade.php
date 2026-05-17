<?php

use App\Models\CbtExam;
use App\Models\CbtQuestion;
use App\Models\Course;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Department;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('CBT Examinations')] class extends Component {
    use WithPagination;

    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public $editingId = null;
    public $deletingId = null;
    
    public function mount(): void
    {
        Gate::authorize('cbt_exams.view');
    }
    
    #[Url]
    public string $search = '';

    public $exam_date = '';
    public $course_id = '';
    public $academic_session_id = '';
    public $semester_id = '';
    public int $duration_minutes = 60;
    public int $total_questions = 50;
    public bool $randomize_questions = true;
    public bool $randomize_options = true;
    public string $status = 'draft';

    // Cascading filters for course selection
    public $filter_program_id = '';
    public $filter_level = '';

    public function updatedAcademicSessionId(): void
    {
        $this->semester_id = '';
        $this->course_id = '';
    }


    public function updatedFilterProgramId(): void
    {
        $this->course_id = '';
    }

    public function updatedFilterLevel(): void
    {
        $this->course_id = '';
    }

    public function save(): void
    {
        if ($this->editingId) {
            Gate::authorize('cbt_exams.edit');
        } else {
            Gate::authorize('cbt_exams.create');
        }

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $this->validate([
            'course_id' => [
                'required',
                'exists:courses,id',
                function ($attribute, $value, $fail) use ($isRestrictedLecturer, $user, $scopedDeptIds) {
                    if ($isRestrictedLecturer) {
                        $isAllocated = \Illuminate\Support\Facades\DB::table('course_allocations')
                            ->where('user_id', $user->id)
                            ->where('course_id', $value)
                            ->exists();
                        if (!$isAllocated) {
                            $fail('You are not allocated to this course.');
                        }
                    } elseif (!empty($scopedDeptIds)) {
                        $course = Course::find($value);
                        if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                            $fail('This course does not belong to your scoped department.');
                        }
                    }
                }
            ],
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'exam_date' => 'required|date',
            'duration_minutes' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
        ]);

        $course = Course::find($this->course_id);
        $semesterName = $course->semester == 1 ? 'first' : 'second';
        $semester = Semester::where('academic_session_id', $this->academic_session_id)
            ->where('name', $semesterName)
            ->first();

        if (!$semester) {
            $this->addError('course_id', 'The semester for this course is not configured in the selected academic session.');
            return;
        }

        $this->semester_id = $semester->id;

        $data = [
            'institution_id' => auth()->user()->institution_id,
            'title' => Course::find($this->course_id)->title,
            'course_id' => $this->course_id,
            'academic_session_id' => $this->academic_session_id,
            'semester_id' => $this->semester_id,
            'exam_date' => $this->exam_date,
            'duration_minutes' => $this->duration_minutes,
            'total_questions' => $this->total_questions,
            'randomize_questions' => $this->randomize_questions,
            'randomize_options' => $this->randomize_options,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            CbtExam::find($this->editingId)->update($data);
            $msg = 'Examination rules updated.';
        } else {
            $data['uuid'] = (string) Str::uuid();
            CbtExam::create($data);
            $msg = 'Examination created successfully.';
        }

        $this->showModal = false;
        $this->reset(['editingId', 'exam_date', 'course_id', 'academic_session_id', 'semester_id', 'duration_minutes', 'total_questions', 'randomize_questions', 'randomize_options', 'status', 'filter_program_id', 'filter_level']);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $msg,
        ]);
    }

    public function edit($id): void
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        if ($isRestrictedLecturer) {
            $isAllocated = \Illuminate\Support\Facades\DB::table('course_allocations')
                ->where('user_id', $user->id)
                ->where('course_id', function ($query) use ($id) {
                    $query->select('course_id')
                        ->from('cbt_exams')
                        ->where('id', $id)
                        ->limit(1);
                })
                ->exists();
            if (!$isAllocated) {
                abort(403, 'Unauthorized action.');
            }
        } elseif (!empty($scopedDeptIds)) {
            $exam = CbtExam::find($id);
            if ($exam) {
                $course = Course::find($exam->course_id);
                if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        $exam = CbtExam::with('course')->findOrFail($id);
        $this->editingId = $exam->id;
        $this->exam_date = $exam->exam_date ? $exam->exam_date->format('Y-m-d') : '';
        $this->course_id = $exam->course_id;
        $this->academic_session_id = $exam->academic_session_id;
        $this->semester_id = $exam->semester_id;
        $this->duration_minutes = $exam->duration_minutes;
        $this->total_questions = $exam->total_questions;
        $this->randomize_questions = $exam->randomize_questions;
        $this->randomize_options = $exam->randomize_options;
        $this->status = $exam->status;
        
        if ($exam->course) {
            $this->filter_program_id = $exam->course->program_id;
            $this->filter_level = $exam->course->level;
        }

        $this->showModal = true;
    }

    public function toggleStatus($id): void
    {
        Gate::authorize('cbt_exams.edit');

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        if ($isRestrictedLecturer) {
            $isAllocated = \Illuminate\Support\Facades\DB::table('course_allocations')
                ->where('user_id', $user->id)
                ->where('course_id', function ($query) use ($id) {
                    $query->select('course_id')
                        ->from('cbt_exams')
                        ->where('id', $id)
                        ->limit(1);
                })
                ->exists();
            if (!$isAllocated) {
                abort(403, 'Unauthorized action.');
            }
        } elseif (!empty($scopedDeptIds)) {
            $exam = CbtExam::find($id);
            if ($exam) {
                $course = Course::find($exam->course_id);
                if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        $exam = CbtExam::findOrFail($id);
        $newStatus = $exam->status === 'active' ? 'draft' : 'active';
        $exam->update(['status' => $newStatus]);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Examination status is now " . ucfirst($newStatus),
        ]);
    }

    public function confirmDelete($id): void
    {
        Gate::authorize('cbt_exams.delete');

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        if ($isRestrictedLecturer) {
            $isAllocated = \Illuminate\Support\Facades\DB::table('course_allocations')
                ->where('user_id', $user->id)
                ->where('course_id', function ($query) use ($id) {
                    $query->select('course_id')
                        ->from('cbt_exams')
                        ->where('id', $id)
                        ->limit(1);
                })
                ->exists();
            if (!$isAllocated) {
                abort(403, 'Unauthorized action.');
            }
        } elseif (!empty($scopedDeptIds)) {
            $exam = CbtExam::find($id);
            if ($exam) {
                $course = Course::find($exam->course_id);
                if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        Gate::authorize('cbt_exams.delete');

        if ($this->deletingId) {
            CbtExam::findOrFail($this->deletingId)->delete();
            $this->showDeleteModal = false;
            $this->deletingId = null;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Examination deleted successfully.',
            ]);
        }
    }

    public function with(): array
    {
        $instId = auth()->user()->institution_id;
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        return [
            'exams' => CbtExam::where('institution_id', $instId)
                ->with(['course', 'academicSession', 'semester'])
                ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('course', fn($cq) => $cq->where('course_code', 'like', "%{$this->search}%")))
                ->when($isRestrictedLecturer, function ($q) use ($user) {
                    $q->whereIn('course_id', function ($sub) use ($user) {
                        $sub->select('course_id')
                            ->from('course_allocations')
                            ->where('user_id', $user->id);
                    });
                })
                ->when(!empty($scopedDeptIds), function ($q) use ($scopedDeptIds) {
                    $q->whereHas('course', function ($cq) use ($scopedDeptIds) {
                        $cq->whereIn('department_id', $scopedDeptIds);
                    });
                })
                ->latest()
                ->paginate(9),
            'programs' => Program::where('institution_id', $instId)->get(),
            'sessions' => AcademicSession::all(),
            'availableCourses' => Course::where('institution_id', $instId)
                ->when($this->filter_program_id, fn($q) => $q->where('program_id', $this->filter_program_id))
                ->when($this->filter_level, fn($q) => $q->where('level', $this->filter_level))
                ->when($this->semester_id, fn($q) => $q->where('semester', $this->semester_id))
                ->when($isRestrictedLecturer, function ($q) use ($user) {
                    $q->whereIn('id', function ($sub) use ($user) {
                        $sub->select('course_id')
                            ->from('course_allocations')
                            ->where('user_id', $user->id);
                    });
                })
                ->when(!empty($scopedDeptIds), fn($q) => $q->whereIn('department_id', $scopedDeptIds))
                ->get(),
        ];
    }

}; ?>

<div class="p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <flux:heading size="xl">{{ __('CBT Examinations') }}</flux:heading>
            <flux:subheading>{{ __('Configure rules and manage the lifecycle of institutional exams.') }}</flux:subheading>
        </div>
        @can('cbt_exams.create')
            <div class="flex-shrink-0">
                <flux:button variant="primary" icon="plus" wire:click="$set('showModal', true)">{{ __('New Examination') }}</flux:button>
            </div>
        @endcan
    </div>

    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search exams by title or course code...') }}" class="w-full md:max-w-md" />
        <flux:spacer class="hidden md:block" />
        <div class="flex items-center gap-2">
            <flux:badge color="zinc" variant="outline">{{ $exams->total() }} {{ __('Total Exams') }}</flux:badge>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($exams as $exam)
            <flux:card class="relative flex flex-col p-0 overflow-hidden hover:shadow-lg transition-shadow duration-300 border-zinc-200 dark:border-zinc-800">
                {{-- Header Color Bar --}}
                <div class="h-1.5 w-full {{ $exam->status === 'active' ? 'bg-green-500' : 'bg-zinc-300 dark:bg-zinc-700' }}"></div>

                <div class="p-6 flex-1 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex flex-col">
                            <flux:heading size="lg" class="mb-1">{{ $exam->title }}</flux:heading>
                            <flux:text size="xs" class="uppercase font-bold tracking-tight text-zinc-500">
                                {{ $exam->academicSession->name }} &bull; {{ $exam->semester->name }} 
                                @if($exam->exam_date) &bull; <span class="text-zinc-700 dark:text-zinc-300">{{ $exam->exam_date->format('M d, Y') }}</span> @endif
                            </flux:text>
                        </div>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                @can('cbt_exams.edit')
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $exam->id }})">{{ __('Edit Rules') }}</flux:menu.item>
                                @endcan
                                <flux:menu.item icon="document-text" :href="route('cms.cbt.questions') . '?selectedExamId=' . $exam->id" wire:navigate>{{ __('Manage Questions') }}</flux:menu.item>
                                <flux:menu.separator />
                                @can('cbt_exams.edit')
                                    <flux:menu.item icon="arrow-path" wire:click="toggleStatus({{ $exam->id }})">
                                        {{ $exam->status === 'active' ? __('Move to Draft') : __('Publish Exam') }}
                                    </flux:menu.item>
                                @endcan
                                @can('cbt_exams.delete')
                                    <flux:menu.item variant="danger" icon="trash" wire:click="confirmDelete({{ $exam->id }})">{{ __('Delete') }}</flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-900 rounded-xl p-3 mb-6 border border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-lg bg-white dark:bg-zinc-800 flex items-center justify-center shadow-sm border border-zinc-200 dark:border-zinc-700">
                                <flux:icon icon="academic-cap" class="size-5 text-blue-600" />
                            </div>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-sm font-bold truncate">{{ $exam->course->title }}</span>
                                <span class="text-[10px] font-mono text-zinc-500 uppercase tracking-widest">{{ $exam->course->course_code }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="flex flex-col">
                            <flux:text size="xs" class="text-zinc-500 uppercase font-bold">{{ __('Duration') }}</flux:text>
                            <span class="text-sm font-medium">{{ $exam->duration_minutes }} {{ __('minutes') }}</span>
                        </div>
                        <div class="flex flex-col">
                            <flux:text size="xs" class="uppercase font-bold text-zinc-500">{{ __('Questions') }}</flux:text>
                            <span class="text-sm font-medium">{{ $exam->total_questions }}</span>
                        </div>
                    </div>

                    <div class="mt-auto pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        @php 
                            $added = $exam->questions_count ?? \App\Models\CbtQuestion::where('cbt_exam_id', $exam->id)->count();
                            $required = $exam->total_questions;
                            $percent = $required > 0 ? min(100, ($added / $required) * 100) : 0;
                        @endphp
                        <div class="flex items-center justify-between mb-2">
                            <flux:text size="xs" class="font-bold text-zinc-500 uppercase">{{ __('Question Bank') }}</flux:text>
                            <span class="text-[10px] font-mono {{ $added >= $required ? 'text-green-600' : 'text-orange-600' }}">
                                {{ $added }} / {{ $required }}
                            </span>
                        </div>
                        <div class="h-1.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full {{ $percent >= 100 ? 'bg-green-500' : 'bg-orange-500' }} transition-all duration-500" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-50/50 dark:bg-zinc-900/50 flex justify-between items-center">
                    <flux:badge :color="$exam->status === 'active' ? 'success' : 'zinc'" size="sm" variant="pill">
                        {{ ucfirst($exam->status) }}
                    </flux:badge>
                    <flux:button variant="ghost" size="xs" :href="route('cms.cbt.questions') . '?selectedExamId=' . $exam->id" wire:navigate class="font-bold">
                        {{ __('Open Bank') }} &rarr;
                    </flux:button>
                </div>
            </flux:card>
        
        @empty
            <div class="col-span-full py-32 flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-900/50 rounded-3xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                <flux:icon icon="clipboard-document-list" class="size-16 text-zinc-300 mb-6" />
                <flux:heading size="lg">{{ __('No Examinations Found') }}</flux:heading>
                <flux:text class="text-zinc-500 mt-2">{{ __('Get started by creating your first exam configuration.') }}</flux:text>
                @can('cbt_exams.create')
                    <flux:button variant="primary" class="mt-6" icon="plus" wire:click="$set('showModal', true)">{{ __('Create Exam') }}</flux:button>
                @endcan
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $exams->links() }}
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-2xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <flux:icon icon="pencil-square" class="size-6 text-blue-600" />
                </div>
                <div>
                    <flux:heading size="lg">{{ $editingId ? __('Refine Examination Rules') : __('New Examination Setup') }}</flux:heading>
                    <flux:subheading>{{ __('Define the constraints and metadata for this CBT session.') }}</flux:subheading>
                </div>
            </div>



            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:select label="{{ __('Academic Session') }}" wire:model.live="academic_session_id">
                    <option value="">{{ __('Select Session') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input type="date" label="{{ __('Exam Date') }}" wire:model="exam_date" required />
            </div>

            <div class="p-5 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-5 shadow-inner">
                <div class="flex items-center gap-2 mb-2">
                    <flux:icon icon="funnel" variant="mini" class="text-zinc-400" />
                    <flux:heading size="sm" class="uppercase tracking-widest text-zinc-500 font-bold">{{ __('Course Selection Cascade') }}</flux:heading>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:select label="{{ __('Program') }}" wire:model.live="filter_program_id">
                        <option value="">{{ __('All Programs') }}</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select label="{{ __('Level') }}" wire:model.live="filter_level">
                        <option value="">{{ __('All Levels') }}</option>
                        @foreach ([100, 200, 300] as $lvl)
                            <option value="{{ $lvl }}">{{ $lvl }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:select label="{{ __('Target Course') }}" wire:model="course_id" required>
                    <option value="">{{ __('-- Select Course --') }}</option>
                    @foreach ($availableCourses as $course)
                        <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->title }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:input type="number" label="{{ __('Duration (Mins)') }}" wire:model="duration_minutes" />
                <flux:input type="number" label="{{ __('Target Questions') }}" wire:model="total_questions" />
                <div class="flex flex-col justify-end pb-2 px-2">
                    <flux:text size="xs" class="text-zinc-500 italic">{{ __('Weight: 70%') }}</flux:text>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
                <flux:card class="flex items-center gap-4 p-4">
                    <flux:checkbox wire:model="randomize_questions" />
                    <div>
                        <flux:label class="font-bold">{{ __('Shuffle Questions') }}</flux:label>
                        <flux:text size="xs">{{ __('Randomize the order of questions for students.') }}</flux:text>
                    </div>
                </flux:card>
                <flux:card class="flex items-center gap-4 p-4">
                    <flux:checkbox wire:model="randomize_options" />
                    <div>
                        <flux:label class="font-bold">{{ __('Shuffle Options') }}</flux:label>
                        <flux:text size="xs">{{ __('Randomize A, B, C, D choices for each student.') }}</flux:text>
                    </div>
                </flux:card>
            </div>

            <div class="flex gap-2 justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="showModal = false">{{ __('Discard Changes') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Examination') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="size-12 rounded-2xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                    <flux:icon icon="trash" class="size-6 text-red-600" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Delete Examination?') }}</flux:heading>
                    <flux:subheading class="mt-2">{{ __('Are you sure you want to permanently delete this CBT examination? This action is irreversible and will delete all associated question banks, options, and related configurations.') }}</flux:subheading>
                </div>
            </div>

            <div class="flex gap-2 justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="showDeleteModal = false">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="executeDelete">{{ __('Permanently Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
