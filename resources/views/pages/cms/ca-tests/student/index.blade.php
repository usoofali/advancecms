<?php

use App\Models\CaTest;
use App\Models\StudentCoin;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Student CA Tests')] class extends Component {
    use WithPagination;

    public bool $showMaxAttemptModal = false;

    public function with(): array
    {
        $student = auth()->user()->student;
        $coins = StudentCoin::where('student_id', $student->id)->value('total_coins') ?? 0;

        $activeSession = \App\Models\AcademicSession::where('status', 'active')->first();

        $tests = CaTest::where('is_published', true)
            ->where('institution_id', auth()->user()->institution_id)
            ->when($activeSession, function ($q) use ($activeSession) {
                $q->where('academic_session_id', $activeSession->id);
            })
            ->whereHas('course', function ($query) use ($student) {
                $query->whereIn('id', function ($sub) use ($student) {
                    $sub->select('course_id')
                        ->from('course_registrations')
                        ->where('student_id', $student->id);
                });
            })
            ->with([
                'results' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                },
                'attempts' => function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                          ->where('status', 'in_progress');
                },
                'questions'
            ])
            ->latest()->paginate(10);

        $activeBlocks = $student->caBlocks()->with('blockedBy')->where('is_resolved', false)->get();
        $globalBlock = $activeBlocks->whereNull('ca_test_id')->first();

        return [
            'tests' => $tests,
            'coins' => $coins,
            'activeBlocks' => $activeBlocks,
            'globalBlock' => $globalBlock,
        ];
    }
}; ?>

<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ __('My CA Tests') }}</flux:heading>
            <flux:subheading>{{ __('Attempt Continuous Assessment tests for your registered courses.') }}
            </flux:subheading>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <flux:button variant="ghost" :href="route('cms.ca-tests.student.leaderboard')" wire:navigate icon="trophy">
            {{ __('View Leaderboard') }}
        </flux:button>
        <div
            class="bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-500 px-4 py-2 rounded-full flex items-center gap-2 font-bold shadow-sm">
            <flux:icon icon="currency-dollar" class="size-5" />
            <span>{{ $coins }} {{ __('Coins') }}</span>
        </div>
    </div>

    @if($globalBlock)
        <div class="mb-8">
            <flux:card class="bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 p-4">
                <div class="flex items-start gap-4">
                    <flux:icon icon="no-symbol" class="size-6 text-red-600 dark:text-red-500 flex-shrink-0" />
                    <div>
                        <h3 class="text-red-800 dark:text-red-400 font-bold text-lg">
                            {{ __('Access Denied (Global Block)') }}
                        </h3>
                        <p class="text-red-700 dark:text-red-300 mt-1">
                            {{ __('You have been blocked from taking any continuous assessments.') }}
                        </p>
                        @if($globalBlock->reason)
                            <p class="text-red-900 dark:text-red-200 mt-2 font-medium">
                                {{ __('Reason:') }} {{ $globalBlock->reason }}
                            </p>
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>
    @endif

    <div class="mt-8">
        <flux:table :paginate="$tests">
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Course') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Score') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($tests as $test)
                    <flux:table.row>
                        <flux:table.cell>{{ $test->title }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $test->course->title }}</span>
                                <span class="text-xs text-zinc-500">{{ $test->course->course_code }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$test->test_type === 'practice' ? 'blue' : 'emerald'">
                                {{ ucfirst($test->test_type) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $result = $test->results->first();
                            @endphp
                            @if($test->show_results && $result)
                                <span class="font-bold text-emerald-600">{{ $result->total_score }}</span>
                                <span class="text-xs text-zinc-500">/ {{ $test->questions->sum('marks') }}</span>
                            @elseif($result)
                                <span class="text-zinc-500 italic">{{ __('Hidden') }}</span>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $testBlocks = $activeBlocks->filter(function($b) use ($test) {
                                    return $b->ca_test_id === null || $b->ca_test_id === $test->id;
                                });
                            @endphp
                            @if($testBlocks->isNotEmpty())
                                <div class="flex flex-col gap-2 items-start max-w-[200px]">
                                    @foreach($testBlocks as $block)
                                        <div class="flex flex-col gap-1 items-start bg-red-50 dark:bg-red-900/20 p-2 rounded-md border border-red-100 dark:border-red-900/50 w-full">
                                            <div class="flex items-center justify-between w-full gap-2">
                                                <flux:badge color="red" size="sm" icon="lock-closed">
                                                    {{ $block->ca_test_id === null ? __('Global') : __('Test') }}
                                                </flux:badge>
                                                <div class="text-[10px] text-zinc-500 truncate" title="{{ $block->blockedBy->name ?? 'Admin' }}">
                                                    {{ $block->blockedBy->name ?? 'Admin' }}
                                                </div>
                                            </div>
                                            @if($block->reason)
                                                <span class="text-xs text-red-600 font-medium">{{ $block->reason }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @php
                                    $inProgressAttempt = $test->attempts->first();
                                    $deadlinePassed = false;
                                    if ($inProgressAttempt && $test->duration_minutes) {
                                        $deadline = $inProgressAttempt->deadline_at ?? $inProgressAttempt->started_at->addMinutes($test->duration_minutes);
                                        $deadlinePassed = now()->greaterThan($deadline);
                                    }
                                @endphp
                                
                                @if($result && $result->attempt_count >= $test->max_attempts)
                                    <flux:button variant="primary" size="sm" wire:click="$set('showMaxAttemptModal', true)">
                                        {{ __('Attempt') }}
                                    </flux:button>
                                @elseif($inProgressAttempt)
                                    @if($test->duration_minutes && $deadlinePassed)
                                        <flux:button variant="danger" size="sm"
                                            :href="route('cms.ca-tests.student.attempt', ['test' => $test->id])" wire:navigate>
                                            {{ __('Submit') }}
                                        </flux:button>
                                    @else
                                        <flux:button variant="primary" size="sm"
                                            :href="route('cms.ca-tests.student.attempt', ['test' => $test->id])" wire:navigate>
                                            {{ __('Resume') }}
                                        </flux:button>
                                    @endif
                                @else
                                    <flux:button variant="primary" size="sm"
                                        :href="route('cms.ca-tests.student.attempt', ['test' => $test->id])" wire:navigate>
                                        {{ __('Attempt') }}
                                    </flux:button>
                                @endif
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No tests available.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showMaxAttemptModal" class="w-full max-w-md">
        <div class="text-center py-4">
            <div class="mx-auto size-20 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mb-6">
                <flux:icon icon="exclamation-triangle" class="size-10" />
            </div>
            <flux:heading size="xl">{{ __('Maximum Attempts Reached') }}</flux:heading>
            <flux:subheading class="mt-2">
                {{ __('You have reached the maximum number of allowed attempts for this test. You cannot take it again.') }}
            </flux:subheading>
            <div class="mt-6 flex justify-center">
                <flux:button variant="primary" wire:click="$set('showMaxAttemptModal', false)">
                    {{ __('Okay, I understand') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>