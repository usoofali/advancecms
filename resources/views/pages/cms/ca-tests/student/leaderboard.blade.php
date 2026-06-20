<?php

use App\Models\CaResult;
use App\Models\StudentCoin;
use App\Models\Course;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('CA Leaderboards')] class extends Component {
    use WithPagination;
    public string $tab = 'coins'; // 'coins' or 'academic'

    #[Url]
    public $course_id = null;

    public function with(): array
    {
        $student = auth()->user()->student;

        $myCourses = Course::whereIn('id', function ($query) use ($student) {
            $query->select('course_id')
                ->from('course_registrations')
                ->where('student_id', $student->id);
        })->get();

        $coinLeaders = [];
        $academicLeaders = [];

        if ($this->tab === 'coins') {
            $coinLeaders = StudentCoin::with('student.user')
                ->orderByDesc('total_coins')
                ->paginate(20);
        } else {
            if ($this->course_id) {
                // To get course leaderboard, we need to sum normalized scores per student for this course
                // For simplicity, we fetch top results for the specific course's tests
                // Or better, we calculate the sum of normalized scores per student.
                $academicLeaders = CaResult::selectRaw('student_id, SUM(normalized_score) as total_normalized')
                    ->whereHas('caTest', function ($q) {
                        $q->where('course_id', $this->course_id);
                    })
                    ->with('student.user')
                    ->groupBy('student_id')
                    ->orderByDesc('total_normalized')
                    ->paginate(20);
            }
        }

        return [
            'myCourses' => $myCourses,
            'coinLeaders' => $coinLeaders,
            'academicLeaders' => $academicLeaders,
            'currentStudentId' => $student->id,
        ];
    }
}; ?>

<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl">{{ __('Leaderboards') }}</flux:heading>
            <flux:subheading>{{ __('See how you rank against your peers.') }}</flux:subheading>
        </div>
        <div>
            <flux:button size="sm" icon="arrow-left" href="{{ route('cms.ca-tests.student.index') }}"
                class="shrink-0" />
        </div>
    </div>

    <div class="mb-6 flex border-b border-zinc-200 dark:border-zinc-800">
        <button wire:click="$set('tab', 'coins')"
            class="px-4 py-2 font-medium text-sm border-b-2 {{ $tab === 'coins' ? 'border-amber-500 text-amber-600 dark:text-amber-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }} flex items-center gap-2">
            <flux:icon icon="currency-dollar" class="size-4" />
            {{ __('Coins Ranking') }}
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
                        <flux:table.row
                            class="{{ $leader->student_id === $currentStudentId ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                            <flux:table.cell>
                                @if($index === 0) 🥇
                                @elseif($index === 1) 🥈
                                @elseif($index === 2) 🥉
                                @else <span class="font-bold text-zinc-400">#{{ $index + 1 }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$leader->student->user->name" />
                                    <span
                                        class="font-medium {{ $leader->student_id === $currentStudentId ? 'text-amber-700 dark:text-amber-400' : '' }}">
                                        {{ $leader->student->user->name }}
                                        @if($leader->student_id === $currentStudentId) (You) @endif
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
                            <flux:table.cell colspan="3" class="text-center text-zinc-500 py-8">{{ __('No coin data yet.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            <div class="mb-6 max-w-sm">
                <flux:select wire:model.live="course_id" label="{{ __('Select Course to view ranking') }}">
                    <option value="">{{ __('-- Select Course --') }}</option>
                    @foreach($myCourses as $course)
                        <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->title }}</option>
                    @endforeach
                </flux:select>
            </div>

            @if($course_id)
                <flux:table :paginate="$academicLeaders">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Student') }}</flux:table.column>
                        <flux:table.column>{{ __('CA Score (Max 30)') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($academicLeaders as $index => $leader)
                            <flux:table.row
                                class="{{ $leader->student_id === $currentStudentId ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}">
                                <flux:table.cell>
                                    <span class="font-bold text-zinc-500">#{{ $index + 1 }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="sm" :name="$leader->student->user->name" />
                                        <span
                                            class="font-medium {{ $leader->student_id === $currentStudentId ? 'text-emerald-700 dark:text-emerald-400' : '' }}">
                                            {{ $leader->student->user->name }}
                                            @if($leader->student_id === $currentStudentId) (You) @endif
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
                                    {{ __('No academic data yet for this course.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            @else
                <div class="text-center py-12 text-zinc-500">
                    {{ __('Please select a course to view the academic ranking.') }}
                </div>
            @endif
        @endif
    </div>
</div>