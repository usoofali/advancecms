<?php

use App\Models\CaTest;
use App\Models\CaAttempt;
use App\Models\CaAnswer;
use App\Services\CaGradingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Attempt CA Test')] class extends Component {
    #[Url]
    public $test = null;

    public $caTest = null;
    public $attempt = null;
    public $questions = [];
    public array $answers = [];
    public bool $isSubmitted = false;
    public bool $maxAttemptsReached = false;
    public ?int $remainingSeconds = null;

    public bool $hasStarted = false;

    public function mount(): void
    {
        if (!$this->test) {
            abort(404, 'Test not found.');
        }

        $this->caTest = CaTest::findOrFail($this->test);
        $this->caTest->load([
            'questions.options' => function ($q) {
                if ($this->caTest->randomize_options) {
                    $q->inRandomOrder();
                }
            }
        ]);

        $student = auth()->user()->student;

        // Check if student is blocked
        $hasBlock = $student->caBlocks()
            ->where('is_resolved', false)
            ->where(function ($query) {
                $query->whereNull('ca_test_id')
                    ->orWhere('ca_test_id', $this->caTest->id);
            })->exists();

        if ($hasBlock) {
            abort(403, 'You are blocked from taking this assessment. Please resolve the block to continue.');
        }

        // Check if student can attempt
        $previousAttempts = CaAttempt::where('ca_test_id', $this->caTest->id)
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();

        if ($previousAttempts >= $this->caTest->max_attempts) {
            $this->maxAttemptsReached = true;
            return;
        }

        // Check for an existing in_progress attempt
        $inProgressAttempt = CaAttempt::where('ca_test_id', $this->caTest->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($inProgressAttempt) {
            $this->attempt = $inProgressAttempt;
            $this->loadAttemptState();
            $this->hasStarted = true;
        }
    }

    public function startTest(): void
    {
        $student = auth()->user()->student;

        $deadline = $this->caTest->duration_minutes
            ? now()->addMinutes($this->caTest->duration_minutes)
            : null;

        $this->attempt = CaAttempt::create([
            'ca_test_id' => $this->caTest->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'deadline_at' => $deadline,
            'status' => 'in_progress',
        ]);

        $this->loadAttemptState();
        $this->hasStarted = true;
    }

    protected function loadAttemptState(): void
    {
        $this->caTest->loadMissing([
            'questions.options' => function ($q) {
                if ($this->caTest->randomize_options) {
                    $q->inRandomOrder();
                }
            }
        ]);

        $qList = $this->caTest->questions;
        if ($this->caTest->randomize_questions) {
            $qList = $qList->shuffle();
        }

        $this->questions = $qList;

        foreach ($this->questions as $q) {
            $this->answers[$q->id] = null;
        }

        if ($this->caTest->duration_minutes) {
            if (!$this->attempt->deadline_at) {
                $this->attempt->deadline_at = $this->attempt->started_at->addMinutes($this->caTest->duration_minutes);
                $this->attempt->save();
            }

            $this->remainingSeconds = max(0, now()->diffInSeconds($this->attempt->deadline_at, false));
            if ($this->remainingSeconds < 0) {
                $this->remainingSeconds = 0;
            }
        }
    }

    public function submitTest(CaGradingService $gradingService): void
    {
        if ($this->isSubmitted)
            return;

        foreach ($this->answers as $questionId => $optionId) {
            CaAnswer::create([
                'ca_attempt_id' => $this->attempt->id,
                'ca_question_id' => $questionId,
                'ca_question_option_id' => $optionId ?: null,
            ]);
        }

        $gradingService->gradeAttempt($this->attempt);
        $this->isSubmitted = true;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Test submitted successfully.',
        ]);
    }
}; ?>

<div class="p-4 md:p-6 max-w-3xl mx-auto">
    @if($maxAttemptsReached)
        <flux:card class="text-center py-12">
            <div class="mx-auto size-20 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mb-6">
                <flux:icon icon="exclamation-triangle" class="size-10" />
            </div>
            <flux:heading size="xl" class="mb-2">{{ __('Maximum Attempts Reached') }}</flux:heading>
            <flux:subheading class="mb-8">
                {{ __('You have reached the maximum number of allowed attempts for this test. You cannot take it again.') }}
            </flux:subheading>

            <flux:button variant="primary" :href="route('cms.ca-tests.student.index')" wire:navigate>
                {{ __('Return to Dashboard') }}
            </flux:button>
        </flux:card>
    @elseif(!$hasStarted)
        <div class="min-h-[60vh] flex flex-col items-center justify-center py-12">
            <flux:card class="max-w-2xl w-full text-center !p-8 md:!p-12">
                <div
                    class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-sm font-medium mb-8">
                    <flux:icon icon="academic-cap" class="size-4" />
                    {{ strtoupper($caTest->course->course_code ?? 'TEST') }}
                </div>

                <flux:heading size="2xl" class="mb-4">{{ $caTest->title ?? 'Assessment' }}</flux:heading>

                <flux:subheading class="text-lg mb-10 max-w-lg mx-auto">
                    {{ $caTest->description ?? 'You are about to start a test for ' . ($caTest->course->title ?? 'this course') . '. Ensure you have a stable internet connection.' }}
                </flux:subheading>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-100 dark:border-zinc-800/80 flex items-center gap-4 text-left">
                        <div class="h-10 w-10 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-sm flex items-center justify-center text-zinc-500">
                            <flux:icon icon="document-text" class="size-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-zinc-900 dark:text-white leading-tight mb-0.5">{{ $caTest->questions->count() }}</span>
                            <span class="text-sm text-zinc-500 font-medium leading-tight">{{ __('Questions') }}</span>
                        </div>
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-100 dark:border-zinc-800/80 flex items-center gap-4 text-left">
                        <div class="h-10 w-10 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-sm flex items-center justify-center text-zinc-500">
                            <flux:icon icon="clock" class="size-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-zinc-900 dark:text-white leading-tight mb-0.5">{{ $caTest->duration_minutes ? $caTest->duration_minutes . ' min' : 'No limit' }}</span>
                            <span class="text-sm text-zinc-500 font-medium leading-tight">{{ __('Duration') }}</span>
                        </div>
                    </div>

                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-100 dark:border-zinc-800/80 flex items-center gap-4 text-left">
                        <div class="h-10 w-10 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-sm flex items-center justify-center text-zinc-500">
                            <flux:icon icon="star" class="size-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-zinc-900 dark:text-white leading-tight mb-0.5">{{ $caTest->questions->sum('marks') }}</span>
                            <span class="text-sm text-zinc-500 font-medium leading-tight">{{ __('Total Points') }}</span>
                        </div>
                    </div>
                    
                    @php
                        $prevAttempts = \App\Models\CaAttempt::where('ca_test_id', $caTest->id)
                            ->where('student_id', auth()->user()->student->id)
                            ->where('status', 'completed')
                            ->count();
                        $currentAttemptNum = $attempt ? "Resume" : ($prevAttempts + 1) . " of " . $caTest->max_attempts;
                    @endphp
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-100 dark:border-zinc-800/80 flex items-center gap-4 text-left">
                        <div class="h-10 w-10 shrink-0 rounded-full bg-white dark:bg-zinc-800 shadow-sm flex items-center justify-center text-zinc-500">
                            <flux:icon icon="arrow-path" class="size-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-zinc-900 dark:text-white leading-tight mb-0.5">{{ $currentAttemptNum }}</span>
                            <span class="text-sm text-zinc-500 font-medium leading-tight">{{ __('Attempt') }}</span>
                        </div>
                    </div>
                </div>

                <flux:button variant="primary" class="w-full sm:w-auto px-10 py-3 h-auto text-lg"
                    icon-trailing="arrow-right" wire:click="startTest">
                    {{ __('Start Test') }}
                </flux:button>
            </flux:card>
        </div>
    @else
        @php
            $payload = [
                'testTitle' => $caTest->title,
                'courseCode' => $caTest->course->course_code,
                'courseTitle' => $caTest->course->title,
                'dashboardUrl' => route('cms.ca-tests.student.index'),
                'submitUrl' => route('cms.ca-tests.student.attempt.submit', ['attempt' => $attempt->id]),
                'extendUrl' => route('cms.ca-tests.student.attempt.extend-time', ['attempt' => $attempt->id]),
                'showResults' => $caTest->show_results,
                'questions' => $questions,
                'remainingSeconds' => $remainingSeconds,
                'attemptId' => $attempt->id,
            ];
        @endphp
        <div id="cbt-app" x-data
            x-init="$nextTick(() => { if (typeof window.initCbtApp === 'function') window.initCbtApp(); })"
            data-payload="{{ json_encode($payload) }}"></div>
    @endif
</div>