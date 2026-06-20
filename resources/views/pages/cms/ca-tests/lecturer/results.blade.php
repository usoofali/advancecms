<?php

use App\Models\CaTest;
use App\Models\CaResult;
use App\Models\Result;
use App\Services\GradingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('CA Test Results')] class extends Component {
    use WithPagination;

    #[Url]
    public $course_id = null;

    #[Url]
    public $test_id = null;

    #[Url]
    public string $search = '';

    public function updatedCourseId()
    {
        $this->test_id = null;
    }

    public bool $showSyncModal = false;
    public string $syncOption = 'sum';

    public function mount(): void
    {
        Gate::authorize('ca_tests.view');
    }

    public function syncResults(): void
    {
        Gate::authorize('ca_tests.edit'); // Requires edit permission to push results

        if (!$this->course_id) return;
        $course = \App\Models\Course::find($this->course_id);
        if (!$course) return;

        // Get all unique students who have a graded CaResult for ANY test in this course
        $studentsToSync = CaResult::whereHas('caTest', function ($query) {
                $query->where('course_id', $this->course_id)
                      ->where('test_type', 'graded');
            })
            ->select('student_id')
            ->distinct()
            ->get()
            ->pluck('student_id');

        if ($studentsToSync->isEmpty()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No graded results found to sync for this course.']);
            $this->showSyncModal = false;
            return;
        }

        DB::transaction(function () use ($studentsToSync, $course) {
            foreach ($studentsToSync as $studentId) {
                // Get all graded tests for this course that the student has taken
                $allStudentResults = CaResult::where('student_id', $studentId)
                    ->whereHas('caTest', function ($query) {
                        $query->where('course_id', $this->course_id)
                              ->where('test_type', 'graded');
                    })->get();
                    
                if ($allStudentResults->isEmpty()) continue;

                $firstTest = $allStudentResults->first()->caTest;
                $finalCaScore = 0.0;

                if ($this->syncOption === 'sum') {
                    // Sum up normalized scores
                    $finalCaScore = $allStudentResults->sum('normalized_score');
                } elseif ($this->syncOption === 'average') {
                    // Average the normalized scores
                    $finalCaScore = $allStudentResults->avg('normalized_score');
                } elseif ($this->syncOption === 'highest') {
                    // Highest normalized score
                    $finalCaScore = $allStudentResults->max('normalized_score');
                }

                // Cap at 30
                $finalCaScore = min(30.0, $finalCaScore);

                // Find or create master Result
                $masterResult = Result::firstOrNew([
                    'student_id' => $studentId,
                    'course_id' => $course->id,
                    'academic_session_id' => $firstTest->academic_session_id,
                    'semester_id' => $firstTest->semester_id,
                ]);

                $masterResult->institution_id = $firstTest->institution_id;
                $masterResult->ca_score = round($finalCaScore, 2);
                $masterResult->total_score = $masterResult->ca_score + ($masterResult->exam_score ?? 0);

                $gradingService = new GradingService();
                $gradingService->grade($masterResult);
                $masterResult->save();

                // Mark all these CA results as synced
                CaResult::whereIn('id', $allStudentResults->pluck('id'))
                    ->update(['synced_at' => now()]);
            }
        });

        $this->showSyncModal = false;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Successfully synced CA scores for ' . $studentsToSync->count() . ' students.',
        ]);
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

        $courses = \App\Models\Course::where('institution_id', $user->institution_id)
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
            ->orderBy('course_code')
            ->get();

        $tests = collect();
        if ($this->course_id) {
            $tests = CaTest::where('course_id', $this->course_id)
                ->where('institution_id', $user->institution_id)
                ->orderByDesc('created_at')
                ->get();
        }

        $selectedTest = null;
        $results = null;

        if ($this->test_id) {
            $selectedTest = CaTest::find($this->test_id);
            if ($selectedTest) {
                $results = CaResult::where('ca_test_id', $this->test_id)
                    ->with('student')
                    ->when($this->search, function ($query) {
                        $query->whereHas('student', function ($sq) {
                            $sq->where('matric_number', 'like', "%{$this->search}%")
                                ->orWhereHas('user', function ($uq) {
                                    $uq->where('name', 'like', "%{$this->search}%");
                                });
                        });
                    })
                    ->orderByDesc('normalized_score')
                    ->paginate(20);
            }
        }

        return [
            'courses' => $courses,
            'tests' => $tests,
            'selectedTest' => $selectedTest,
            'results' => $results,
            'selectedCourse' => $this->course_id ? \App\Models\Course::find($this->course_id) : null,
        ];
    }
}; ?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl">{{ __('Test Results') }}</flux:heading>
            <flux:subheading>{{ __('View student performance and normalized scores.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" :href="route('cms.ca-tests.lecturer.index')" wire:navigate icon="arrow-left">
            {{ __('Back to CA Tests') }}
        </flux:button>
    </div>

    <div class="mb-8 flex flex-col sm:flex-row items-end gap-4">
        <div class="w-full sm:w-1/2">
            <flux:select wire:model.live="course_id" label="{{ __('Select Course') }}">
                <option value="">{{ __('-- Choose a Course --') }}</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->title }}</option>
                @endforeach
            </flux:select>
        </div>

        @if($course_id)
            <div class="w-full sm:w-1/2">
                <flux:select wire:model.live="test_id" label="{{ __('Select CA Test') }}">
                    <option value="">{{ __('-- Choose a Test to View Results --') }}</option>
                    @foreach($tests as $test)
                        <option value="{{ $test->id }}">{{ $test->title }} ({{ ucfirst($test->test_type) }})</option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    @if($course_id && $selectedCourse)
        <div class="mb-6 flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-100 dark:border-zinc-800">
            <div>
                <flux:heading size="md">{{ __('Course Sync Operations') }}</flux:heading>
                <flux:subheading size="sm">{{ __('Aggregate and sync all graded CA tests for this course into the master result sheet.') }}</flux:subheading>
            </div>
            @can('ca_tests.edit')
                <flux:button variant="primary" icon="arrow-path" wire:click="$set('showSyncModal', true)">
                    {{ __('Sync Course Scores') }}
                </flux:button>
            @endcan
        </div>
    @endif

    @if($selectedTest)
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Results for :title', ['title' => $selectedTest->title]) }}</flux:heading>
            <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search students...') }}" class="w-full sm:w-64" />
        </div>

        <flux:table :paginate="$results">
            <flux:table.columns>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Matric No') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Raw Score') }}</flux:table.column>
                <flux:table.column>{{ __('Normalized Score') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($results as $result)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$result->student->user->name" />
                                <span class="font-medium">{{ $result->student->user->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $result->student->matric_number }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc">{{ $result->attempt_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-zinc-600">{{ $result->total_score }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-bold text-emerald-600">{{ $result->normalized_score }} / 30</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($result->synced_at)
                                <div class="flex flex-col">
                                    <flux:badge color="green" size="sm">{{ __('Synced') }}</flux:badge>
                                    <span class="text-[10px] text-zinc-400 mt-1">{{ $result->synced_at->format('M d, y H:i') }}</span>
                                </div>
                            @else
                                <flux:badge color="orange" size="sm">{{ __('Pending') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                            {{ __('No results found for this test.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:modal wire:model="showSyncModal" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Sync CA Scores') }}</flux:heading>
                    <flux:subheading>{{ __('Push these results to the master academic records.') }}</flux:subheading>
                </div>

                <div class="space-y-2">
                    <flux:label>{{ __('Calculation Method') }}</flux:label>
                    <flux:text size="sm" class="mb-2 text-zinc-500">
                        {{ __('If a student has taken multiple CA tests for this course, how should their final CA score (out of 30) be calculated?') }}
                    </flux:text>
                    <flux:radio.group wire:model="syncOption">
                        <flux:radio value="sum" label="{{ __('Sum / Cumulative') }}" description="{{ __('Add normalized scores together (max 30).') }}" />
                        <flux:radio value="average" label="{{ __('Average') }}" description="{{ __('Average the normalized scores.') }}" />
                        <flux:radio value="highest" label="{{ __('Highest Score') }}" description="{{ __('Take the highest normalized score.') }}" />
                    </flux:radio.group>
                </div>

                <flux:callout variant="warning">
                    {{ __('This will overwrite any existing CA score for these students in the master Result records. This action can be repeated later if scores change.') }}
                </flux:callout>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="syncResults">{{ __('Sync Results') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>