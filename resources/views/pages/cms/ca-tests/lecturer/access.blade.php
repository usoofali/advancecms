<?php

use App\Models\CaTest;
use App\Models\CaBlock;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage CA Access')] class extends Component {
    use WithPagination;

    #[Url]
    public $test_id = null;

    public $reason = '';
    public $showBlockModal = false;
    public $studentToBlock = null;
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        Gate::authorize('ca_tests.edit');
    }

    public function getSelectedTestProperty()
    {
        if (!$this->test_id)
            return null;

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        return CaTest::where('id', $this->test_id)
            ->where('institution_id', $user->institution_id)
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
            ->first();
    }

    public function openBlockModal($studentId)
    {
        $this->studentToBlock = $studentId;
        $this->reason = '';
        $this->showBlockModal = true;
    }

    public function blockStudent()
    {
        $this->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        CaBlock::create([
            'student_id' => $this->studentToBlock,
            'ca_test_id' => $this->test_id, // Specific block for this test
            'blocked_by_id' => auth()->id(),
            'reason' => $this->reason,
            'is_resolved' => false,
        ]);

        $this->showBlockModal = false;
        $this->studentToBlock = null;
        $this->reason = '';
        Flux::toast('Student has been blocked from this test.');
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Student has been blocked from this test.']);
    }

    public function unblockStudent($blockId)
    {
        $block = CaBlock::find($blockId);
        if ($block && !$block->is_resolved) {
            $user = auth()->user();
            if ($block->ca_test_id === null && !$user->hasRole('Super Admin') && !$user->hasRole('Institutional Admin')) {
                Flux::toast('You are not authorized to remove global blocks. Please contact an admin.', variant: 'danger');
                return;
            }

            if ($block->ca_test_id !== null && $block->blocked_by_id !== $user->id && !$user->hasRole('Super Admin') && !$user->hasRole('Institutional Admin')) {
                Flux::toast('You can only remove blocks that you created.', variant: 'danger');
                return;
            }

            $block->update([
                'is_resolved' => true,
                'resolved_by_id' => auth()->id(),
                'resolved_at' => now(),
            ]);
            Flux::toast('Block resolved successfully.');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Block resolved successfully.']);
        }
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

        $tests = CaTest::where('institution_id', $user->institution_id)
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
            ->get();

        $students = collect();
        $test = $this->selectedTest;

        if ($test) {
            $students = CourseRegistration::where('course_id', $test->course_id)
                ->where('academic_session_id', $test->academic_session_id)
                ->with([
                    'student.user',
                    'student.caBlocks' => function ($query) use ($test) {
                        $query->where('is_resolved', false)
                            ->where(function ($q) use ($test) {
                                $q->whereNull('ca_test_id')->orWhere('ca_test_id', $test->id);
                            })->with('blockedBy');
                    }
                ])
                ->when($this->search, function ($query) {
                    $query->whereHas('student', function ($sq) {
                        $sq->where('matric_number', 'like', '%' . $this->search . '%')
                           ->orWhereHas('user', function ($uq) {
                               $uq->where('name', 'like', '%' . $this->search . '%');
                           });
                    });
                })
                ->paginate(20);
        }

        return [
            'tests' => $tests,
            'students' => $students,
            'selectedTest' => $test,
        ];
    }
}; ?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl">{{ __('Manage CA Access') }}</flux:heading>
            <flux:subheading>{{ __('Control student access to continuous assessment tests.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" :href="route('cms.ca-tests.lecturer.index')" wire:navigate icon="arrow-left">
            {{ __('Back to CA Tests') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:select wire:model.live="test_id" label="{{ __('Select CA Test to manage access') }}">
            <option value="">{{ __('-- Choose a Test --') }}</option>
            @foreach($tests as $test)
                <option value="{{ $test->id }}">{{ $test->title }} ({{ $test->course->course_code }})</option>
            @endforeach
        </flux:select>
    </div>

    @if($selectedTest)
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Registered Students for :title', ['title' => $selectedTest->title]) }}
            </flux:heading>
            <div class="w-full sm:w-64">
                <flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or matric...') }}" icon="magnifying-glass" />
            </div>
        </div>

        <flux:table :paginate="$students">
            <flux:table.columns>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Matric No') }}</flux:table.column>
                <flux:table.column>{{ __('Access Status') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($students as $registration)
                    @php
                        $activeBlocks = $registration->student->caBlocks;
                        $hasUserBlocked = $activeBlocks->where('ca_test_id', $this->test_id)->where('blocked_by_id', auth()->id())->isNotEmpty();
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$registration->student->user->name" />
                                <span class="font-medium">{{ $registration->student->user->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $registration->student->matric_number }}</flux:table.cell>
                        <flux:table.cell>
                            @if($activeBlocks->count() > 0)
                                <div class="flex flex-col gap-1.5 max-w-[250px]">
                                    @foreach($activeBlocks as $block)
                                        <div class="flex flex-col text-xs p-2 bg-red-50 dark:bg-red-900/20 rounded-md border border-red-100 dark:border-red-900/50">
                                            <div class="flex items-center gap-2">
                                                <div class="font-bold text-red-700 dark:text-red-400">
                                                    {{ $block->ca_test_id === null ? __('Global') : __('Test') }}
                                                </div>
                                                <div class="text-[10px] text-zinc-500 truncate" title="{{ $block->blockedBy->name ?? 'Admin' }}">
                                                    {{ $block->blockedBy->name ?? 'Admin' }}
                                                </div>
                                                
                                                @if(
                                                    ($block->ca_test_id === null && (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Institutional Admin'))) ||
                                                    ($block->ca_test_id !== null && ($block->blocked_by_id === auth()->id() || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Institutional Admin')))
                                                )
                                                    <button type="button" class="ml-auto text-emerald-600 hover:text-emerald-700 font-medium px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 rounded" wire:click="unblockStudent({{ $block->id }})">
                                                        {{ __('Unblock') }}
                                                    </button>
                                                @endif
                                            </div>
                                            @if($block->reason)
                                                <div class="text-red-600 dark:text-red-400 mt-1 truncate" title="{{ $block->reason }}">{{ $block->reason }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <flux:badge color="emerald" size="sm">{{ __('Allowed') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if(!$hasUserBlocked)
                                <flux:button variant="ghost" size="sm" icon="lock-closed"
                                    class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/30"
                                    wire:click="openBlockModal({{ $registration->student_id }})">
                                    {{ __('Add Block') }}
                                </flux:button>
                            @else
                                <span class="text-sm text-zinc-500 italic">{{ __('Block Applied') }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                            {{ __('No students registered for this course/session.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showBlockModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Block Student') }}</flux:heading>
                <flux:subheading>{{ __('Restrict this student from taking the CA test.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason for blocking') }} <span
                        class="text-zinc-400 font-normal ml-1">{{ __('(Optional)') }}</span></flux:label>
                <flux:textarea wire:model="reason"
                    placeholder="{{ __('e.g., Disciplinary action, missing prerequisites...') }}" rows="3" />
                <flux:error name="reason" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="blockStudent">{{ __('Block Access') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>