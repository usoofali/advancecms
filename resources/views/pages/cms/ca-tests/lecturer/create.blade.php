<?php

use App\Models\CaTest;
use App\Models\Course;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Department;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Create/Edit CA Test')] class extends Component {
    #[Url]
    public $edit = null;

    public string $title = '';
    public string $description = '';

    // Scoped Cascade
    public $academic_session_id = '';
    public $semester_id = '';
    public $course_id = '';
    public $filter_program_id = '';
    public $filter_level = '';

    public string $test_type = 'graded';
    public int $duration_minutes = 30;
    public int $max_attempts = 1;
    public bool $is_published = false;
    public bool $coin_reward_enabled = true;
    public bool $randomize_questions = true;
    public bool $randomize_options = true;
    public bool $show_results = true;
    public string $start_date = '';
    public string $end_date = '';

    public function mount(): void
    {
        Gate::authorize('ca_tests.create'); // Or ca_tests.edit if edit

        if ($this->edit) {
            $test = CaTest::findOrFail($this->edit);
            // Verify access to this test? The backend will protect on save.
            $this->title = $test->title;
            $this->description = $test->description ?? '';
            $this->academic_session_id = $test->academic_session_id;
            $this->semester_id = $test->semester_id;
            $this->course_id = $test->course_id;
            // Get program and level from course to pre-populate filters
            $this->filter_program_id = $test->course->program_id ?? '';
            $this->filter_level = $test->course->level ?? '';
            
            $this->test_type = $test->test_type;
            $this->duration_minutes = $test->duration_minutes ?? 30;
            $this->max_attempts = $test->max_attempts;
            $this->is_published = $test->is_published;
            $this->coin_reward_enabled = $test->coin_reward_enabled;
            $this->randomize_questions = $test->randomize_questions ?? true;
            $this->randomize_options = $test->randomize_options ?? true;
            $this->show_results = $test->show_results ?? true;
            $this->start_date = $test->start_date ? $test->start_date->format('Y-m-d\TH:i') : '';
            $this->end_date = $test->end_date ? $test->end_date->format('Y-m-d\TH:i') : '';
        }
    }

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
            'programs' => Program::where('institution_id', $instId)->get(),
            'sessions' => AcademicSession::all(),
            'availableCourses' => Course::where('institution_id', $instId)
                ->when($this->filter_program_id, fn($q) => $q->where('program_id', $this->filter_program_id))
                ->when($this->filter_level, fn($q) => $q->where('level', $this->filter_level))
                ->when(!$isSuperAdmin && !$isInstAdmin, function ($q) use ($user, $scopedDeptIds) {
                    $q->where(function ($subQ) use ($user, $scopedDeptIds) {
                        $subQ->whereIn('id', function ($allocQ) use ($user) {
                            $allocQ->select('course_id')
                                ->from('course_allocations')
                                ->where('user_id', $user->id);
                        });

                        if (!empty($scopedDeptIds)) {
                            $subQ->orWhereIn('department_id', $scopedDeptIds);
                        }
                    });
                })
                ->orderBy('semester')
                ->orderBy('course_code')
                ->get(),
        ];
    }

    public function save(): void
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

        $this->validate([
            'title' => 'required|string|max:255',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'course_id' => [
                'required',
                'exists:courses,id',
                function ($attribute, $value, $fail) use ($user, $scopedDeptIds, $isSuperAdmin, $isInstAdmin) {
                    if (!$isSuperAdmin && !$isInstAdmin) {
                        $isAllocated = \Illuminate\Support\Facades\DB::table('course_allocations')
                            ->where('user_id', $user->id)
                            ->where('course_id', $value)
                            ->exists();
                            
                        $course = \App\Models\Course::find($value);
                        $isScoped = $course && !empty($scopedDeptIds) && in_array($course->department_id, $scopedDeptIds);

                        if (!$isAllocated && !$isScoped) {
                            $fail('You are not authorized to create a test for this course.');
                        }
                    }
                }
            ],
            'test_type' => 'required|in:graded,practice',
            'duration_minutes' => 'nullable|integer|min:1',
            'max_attempts' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
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
            'institution_id' => $user->institution_id,
            'academic_session_id' => $this->academic_session_id,
            'semester_id' => $this->semester_id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes ?: null,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'test_type' => $this->test_type,
            'max_attempts' => $this->max_attempts,
            'is_published' => $this->is_published,
            'coin_reward_enabled' => $this->coin_reward_enabled,
            'randomize_questions' => $this->randomize_questions,
            'randomize_options' => $this->randomize_options,
            'show_results' => $this->show_results,
        ];

        if ($this->edit) {
            $test = CaTest::findOrFail($this->edit);
            $test->update($data);
            $message = 'CA Test updated successfully.';
        } else {
            CaTest::create($data);
            $message = 'CA Test created successfully.';
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);

        $this->redirectRoute('cms.ca-tests.lecturer.index', navigate: true);
    }
}; ?>

<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl">{{ $edit ? __('Edit CA Test') : __('Create CA Test') }}</flux:heading>
            <flux:subheading>{{ $edit ? __('Update the settings for this Continuous Assessment.') : __('Setup a new Continuous Assessment for your students.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" :href="route('cms.ca-tests.lecturer.index')" wire:navigate icon="arrow-left">
            {{ __('Back to Tests') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Test & Academic Hierarchy') }}</flux:heading>

                <flux:input wire:model="title" label="{{ __('Test Title') }}"
                    placeholder="{{ __('e.g., Mid-Semester CA 1') }}" required />

                <div
                    class="p-5 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 space-y-5 shadow-inner">
                    <div class="flex items-center gap-2 mb-2">
                        <flux:icon icon="funnel" variant="mini" class="text-zinc-400" />
                        <flux:heading size="sm" class="uppercase tracking-widest text-zinc-500 font-bold">
                            {{ __('Course Selection Cascade') }}
                        </flux:heading>
                    </div>

                    <flux:select label="{{ __('Academic Session') }}" wire:model.live="academic_session_id" required>
                        <option value="">{{ __('Select Session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </flux:select>

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
                        @foreach ($availableCourses->sortBy(['semester', 'course_code']) as $course)
                            <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->title }} (Semester:
                                {{ $course->semester }})
                            </option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:textarea wire:model="description" label="{{ __('Description / Instructions') }}"
                    placeholder="{{ __('Instructions for students...') }}" />
            </div>
        </flux:card>

        <flux:card>
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Settings & Constraints') }}</flux:heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <flux:select wire:model="test_type" label="{{ __('Test Type') }}" required>
                        <option value="graded">{{ __('Graded (Recorded)') }}</option>
                        <option value="practice">{{ __('Practice (Not Recorded)') }}</option>
                    </flux:select>

                    <flux:input type="number" wire:model="duration_minutes" label="{{ __('Duration (Mins)') }}"
                        placeholder="{{ __('Leave blank for untimed') }}" />

                    <flux:input type="number" wire:model="max_attempts" label="{{ __('Max Attempts') }}" required />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:input type="datetime-local" wire:model="start_date" label="{{ __('Available From') }}" />
                    <flux:input type="datetime-local" wire:model="end_date" label="{{ __('Available Until') }}" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:checkbox wire:model="coin_reward_enabled" label="{{ __('Enable Coin Rewards') }}"
                        description="{{ __('Award coins for correct answers.') }}" />
                    <flux:checkbox wire:model="is_published" label="{{ __('Publish Immediately') }}"
                        description="{{ __('Make this test visible to students.') }}" />
                    <flux:checkbox wire:model="show_results" label="{{ __('Show Results') }}"
                        description="{{ __('Allow students to see their score upon submission.') }}" />
                </div>
            </div>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('cms.ca-tests.lecturer.index')" wire:navigate>{{ __('Cancel') }}
            </flux:button>
            <flux:button variant="primary" type="submit" icon="check">{{ $edit ? __('Update CA Test') : __('Save CA Test') }}</flux:button>
        </div>
    </form>
</div>