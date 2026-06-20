<?php

use App\Models\CaTest;
use App\Models\Department;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage CA Tests')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter_session = '';

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
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $tests = CaTest::where('institution_id', $user->institution_id)
            ->with(['course', 'academicSession', 'semester'])
            ->withCount('questions')
            ->when($this->filter_session, fn($q) => $q->where('academic_session_id', $this->filter_session))
            ->when($this->search, function($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhereHas('course', fn($cq) => $cq->where('course_code', 'like', "%{$this->search}%"));
            })
            ->when(!$isSuperAdmin && !$isInstAdmin, function ($q) use ($user, $scopedDeptIds) {
                $q->where(function ($subQ) use ($user, $scopedDeptIds) {
                    $subQ->whereIn('course_id', function ($allocQ) use ($user) {
                        $allocQ->select('course_id')
                            ->from('course_allocations')
                            ->where('user_id', $user->id);
                    });

                    if (!empty($scopedDeptIds)) {
                        $subQ->orWhereHas('course', function ($cq) use ($scopedDeptIds) {
                            $cq->whereIn('department_id', $scopedDeptIds);
                        });
                    }
                });
            })
            ->latest()
            ->paginate(15);

        return [
            'tests' => $tests,
            'sessions' => AcademicSession::all(),
        ];
    }
}; ?>

<div class="p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <flux:heading size="xl">{{ __('Manage CA Tests') }}</flux:heading>
            <flux:subheading>{{ __('Setup and monitor Continuous Assessment tests.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            @can('ca_tests.view')
                <flux:button variant="ghost" icon="trophy" :href="route('cms.ca-tests.lecturer.leaderboard')" wire:navigate>
                    {{ __('Leaderboards') }}
                </flux:button>
            @endcan
            @can('ca_tests.create')
                <div class="flex-shrink-0">
                    <flux:button variant="primary" icon="plus" :href="route('cms.ca-tests.lecturer.create')" wire:navigate>
                        {{ __('Create Test') }}
                    </flux:button>
                </div>
            @endcan
        </div>
    </div>

    <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('Search tests by title or course code...') }}" class="w-full md:max-w-md" />
        <div class="w-full md:w-64">
            <flux:select wire:model.live="filter_session">
                <option value="">{{ __('All Academic Sessions') }}</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </flux:select>
        </div>
        <flux:spacer class="hidden md:block" />
        <div class="flex items-center gap-2">
            <flux:badge color="zinc" variant="outline">{{ $tests->total() }} {{ __('Total Tests') }}</flux:badge>
        </div>
    </div>

    <flux:table :paginate="$tests">
        <flux:table.columns>
            <flux:table.column>{{ __('Test Title / Course') }}</flux:table.column>
            <flux:table.column>{{ __('Term') }}</flux:table.column>
            <flux:table.column>{{ __('Type / Attempts') }}</flux:table.column>
            <flux:table.column>{{ __('Questions / Duration') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($tests as $test)
                <flux:table.row :key="$test->id">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <span class="font-bold text-zinc-900 dark:text-white">{{ $test->title }}</span>
                            <span class="text-sm text-zinc-500">{{ $test->course->course_code }} - {{ $test->course->title }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($test->academicSession)
                        <div class="flex flex-col">
                            <span class="text-sm text-zinc-900 dark:text-white">{{ $test->academicSession->name }}</span>
                            <span class="text-xs text-zinc-500 capitalize">{{ $test->semester->name ?? '' }} {{ __('Semester') }}</span>
                        </div>
                        @else
                        <span class="text-xs text-zinc-400 italic">{{ __('Unassigned') }}</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1 items-start">
                            <flux:badge :color="$test->test_type === 'practice' ? 'blue' : 'emerald'" size="sm">
                                {{ ucfirst($test->test_type) }}
                            </flux:badge>
                            <span class="text-xs text-zinc-500">{{ __('Max Attempts:') }} {{ $test->max_attempts }}</span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1 items-start">
                            <span class="text-sm font-bold text-zinc-900 dark:text-white">
                                {{ $test->questions_count }} {{ __('Questions') }}
                            </span>
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                <flux:icon icon="clock" class="size-3 inline-block mr-1" />
                                {{ $test->duration_minutes ? $test->duration_minutes . ' mins' : __('Untimed') }}
                            </span>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge :color="$test->is_published ? 'success' : 'zinc'" size="sm" variant="pill">
                            {{ $test->is_published ? __('Published') : __('Draft') }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                @can('ca_tests.edit')
                                    <flux:menu.item icon="pencil-square" :href="route('cms.ca-tests.lecturer.create') . '?edit=' . $test->id" wire:navigate>
                                        {{ __('Edit Rules') }}
                                    </flux:menu.item>
                                @endcan
                                <flux:menu.item icon="question-mark-circle" :href="route('cms.ca-tests.lecturer.questions') . '?test_id=' . $test->id" wire:navigate>
                                    {{ __('Manage Questions') }}
                                </flux:menu.item>
                                <flux:menu.item icon="lock-closed" :href="route('cms.ca-tests.lecturer.access') . '?test_id=' . $test->id" wire:navigate>
                                    {{ __('Manage Access') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-20 text-zinc-500">
                        <flux:icon icon="document-text" class="size-12 mx-auto mb-4 opacity-20" />
                        <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('No CA Tests Found') }}</p>
                        <p class="text-sm text-zinc-500 mt-1">{{ __('Get started by creating your first continuous assessment.') }}</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
