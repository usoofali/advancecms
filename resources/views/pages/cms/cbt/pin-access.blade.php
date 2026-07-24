<?php

use App\Models\AcademicSession;
use App\Models\CbtPinAccessControl;
use App\Models\Program;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('CBT PIN Access Control')] class extends Component {
    public $session_id = '';

    public $semester_id = '';

    public $search = '';

    public function mount(): void
    {
        Gate::authorize('cbt_exams.view');

        $activeSession = AcademicSession::where('status', 'active')->first()
            ?? AcademicSession::latest()->first();

        if ($activeSession) {
            $this->session_id = $activeSession->id;
            $firstSemester = Semester::where('academic_session_id', $activeSession->id)->first();
            if ($firstSemester) {
                $this->semester_id = $firstSemester->id;
            }
        }
    }

    public function updatedSessionId($value): void
    {
        if ($value) {
            $firstSemester = Semester::where('academic_session_id', $value)->first();
            $this->semester_id = $firstSemester?->id ?? '';
        } else {
            $this->semester_id = '';
        }
    }

    public function toggleGlobalUnlock(): void
    {
        Gate::authorize('cbt_exams.view');
        if (!$this->session_id || !$this->semester_id) {
            return;
        }

        $instId = auth()->user()->institution_id;
        $current = CbtPinAccessControl::isUnlocked($instId, (int)$this->session_id, (int)$this->semester_id);
        $newStatus = !$current;

        CbtPinAccessControl::updateOrCreate(
            [
                'institution_id' => $instId,
                'academic_session_id' => $this->session_id,
                'semester_id' => $this->semester_id,
                'program_id' => null,
            ],
            [
                'is_unlocked' => $newStatus,
                'unlocked_at' => $newStatus ? now() : null,
                'updated_by' => auth()->id(),
            ]
        );

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $newStatus
                ? __('Global CBT PIN access unlocked for all programs in this session and semester.')
                : __('Global CBT PIN access locked for this session and semester.'),
        ]);
    }

    public function toggleProgramUnlock(int $programId): void
    {
        Gate::authorize('cbt_exams.view');
        if (!$this->session_id || !$this->semester_id) {
            return;
        }

        $instId = auth()->user()->institution_id;
        $currentStatus = CbtPinAccessControl::isUnlocked($instId, (int)$this->session_id, (int)$this->semester_id, $programId);
        $newStatus = !$currentStatus;

        CbtPinAccessControl::updateOrCreate(
            [
                'institution_id' => $instId,
                'academic_session_id' => $this->session_id,
                'semester_id' => $this->semester_id,
                'program_id' => $programId,
            ],
            [
                'is_unlocked' => $newStatus,
                'unlocked_at' => $newStatus ? now() : null,
                'updated_by' => auth()->id(),
            ]
        );

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $newStatus
                ? __('CBT PIN access unlocked for selected program.')
                : __('CBT PIN access locked for selected program.'),
        ]);
    }

    public function setAllProgramsStatus(bool $unlocked): void
    {
        Gate::authorize('cbt_exams.view');
        if (!$this->session_id || !$this->semester_id) {
            return;
        }

        $instId = auth()->user()->institution_id;
        $programs = Program::where('institution_id', $instId)->get();

        foreach ($programs as $prog) {
            CbtPinAccessControl::updateOrCreate(
                [
                    'institution_id' => $instId,
                    'academic_session_id' => $this->session_id,
                    'semester_id' => $this->semester_id,
                    'program_id' => $prog->id,
                ],
                [
                    'is_unlocked' => $unlocked,
                    'unlocked_at' => $unlocked ? now() : null,
                    'updated_by' => auth()->id(),
                ]
            );
        }

        // Also set global level
        CbtPinAccessControl::updateOrCreate(
            [
                'institution_id' => $instId,
                'academic_session_id' => $this->session_id,
                'semester_id' => $this->semester_id,
                'program_id' => null,
            ],
            [
                'is_unlocked' => $unlocked,
                'unlocked_at' => $unlocked ? now() : null,
                'updated_by' => auth()->id(),
            ]
        );

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $unlocked
                ? __('CBT PIN access unlocked for all programs.')
                : __('CBT PIN access locked for all programs.'),
        ]);
    }

    public function with(): array
    {
        $instId = auth()->user()->institution_id;
        $sessions = AcademicSession::orderBy('name', 'desc')->get();
        $semesters = $this->session_id
            ? Semester::where('academic_session_id', $this->session_id)->get()
            : collect();

        $programsQuery = Program::where('institution_id', $instId)->with('department');
        if ($this->search) {
            $programsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('acronym', 'like', '%' . $this->search . '%');
            });
        }
        $programs = $programsQuery->get();

        $isGlobalUnlocked = false;
        $unlockedProgramIds = [];

        if ($this->session_id && $this->semester_id) {
            $isGlobalUnlocked = CbtPinAccessControl::isUnlocked($instId, (int)$this->session_id, (int)$this->semester_id);

            foreach ($programs as $prog) {
                if (CbtPinAccessControl::isUnlocked($instId, (int)$this->session_id, (int)$this->semester_id, $prog->id)) {
                    $unlockedProgramIds[] = $prog->id;
                }
            }
        }

        return [
            'sessions' => $sessions,
            'semesters' => $semesters,
            'programs' => $programs,
            'isGlobalUnlocked' => $isGlobalUnlocked,
            'unlockedProgramIds' => $unlockedProgramIds,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="flex items-center gap-2">
                <flux:icon.key class="size-7 text-blue-600 dark:text-blue-400" />
                {{ __('CBT PIN Access Control') }}
            </flux:heading>

            <flux:subheading>
                {{ __('Manage student CBT PIN visibility on Examination Cards. Locked by default per Academic Session, Semester & Program.') }}
            </flux:subheading>
        </div>

        @if ($session_id && $semester_id)
            <div class="flex items-center gap-3">
                <flux:button variant="filled" icon="lock-closed" wire:click="setAllProgramsStatus(false)">
                    {{ __('Lock All') }}
                </flux:button>
                <flux:button variant="primary" icon="lock-open" wire:click="setAllProgramsStatus(true)">
                    {{ __('Unlock All') }}
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Filter Card --}}
    <flux:card class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:select wire:model.live="session_id" :label="__('Academic Session')">
                <option value="">{{ __('Select Session') }}</option>
                @foreach ($sessions as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} {{ $s->status === 'active' ? __('(Active)') : '' }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" :disabled="!$session_id">
                <option value="">{{ __('Select Semester') }}</option>
                @foreach ($semesters as $sem)
                    <option value="{{ $sem->id }}">{{ ucfirst($sem->name) }} {{ __('Semester') }}</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    @if ($session_id && $semester_id)
        {{-- Global Controls & Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Global State Card --}}
            <flux:card class="md:col-span-2 space-y-4 border-2 {{ $isGlobalUnlocked ? 'border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/20' : 'border-amber-200 dark:border-amber-900/50 bg-amber-50/20' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('Global Session & Semester Lock Status') }}</span>
                        <h3 class="text-lg font-black text-zinc-900 dark:text-white mt-1">
                            @if ($isGlobalUnlocked)
                                <span class="text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                    <flux:icon.lock-open class="size-5" />
                                    {{ __('UNLOCKED (PIN Visible On Exam Cards)') }}
                                </span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                    <flux:icon.lock-closed class="size-5" />
                                    {{ __('LOCKED BY DEFAULT (PIN Coated & Masked)') }}
                                </span>
                            @endif
                        </h3>
                    </div>

                    <flux:button
                        variant="{{ $isGlobalUnlocked ? 'filled' : 'primary' }}"
                        wire:click="toggleGlobalUnlock"
                        icon="{{ $isGlobalUnlocked ? 'lock-closed' : 'lock-open' }}"
                    >
                        {{ $isGlobalUnlocked ? __('Lock Global Access') : __('Unlock Global Access') }}
                    </flux:button>
                </div>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                    {{ __('Unlocking global access allows all programs to view their CBT PIN unless specifically overridden below.') }}
                </p>
            </flux:card>

            {{-- Summary Stats --}}
            <flux:card class="flex flex-col justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('Program Controls Summary') }}</span>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50">
                        <span class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400">{{ __('Unlocked') }}</span>
                        <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ count($unlockedProgramIds) }}</div>
                    </div>
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50">
                        <span class="text-[10px] font-bold uppercase text-amber-600 dark:text-amber-400">{{ __('Locked') }}</span>
                        <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{{ count($programs) - count($unlockedProgramIds) }}</div>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Program Level Overrides Table --}}
        <flux:card class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                <div>
                    <flux:heading size="lg">{{ __('Program PIN Access Controls') }}</flux:heading>
                    <flux:subheading>{{ __('Override PIN visibility for individual academic programs.') }}</flux:subheading>
                </div>
                <div class="w-full sm:w-64">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search program...') }}" icon="magnifying-glass" />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50">
                            <th class="py-3 px-4 font-bold text-xs uppercase text-zinc-500">{{ __('Program Code & Name') }}</th>
                            <th class="py-3 px-4 font-bold text-xs uppercase text-zinc-500">{{ __('Department') }}</th>
                            <th class="py-3 px-4 font-bold text-xs uppercase text-zinc-500 text-center">{{ __('PIN Status') }}</th>
                            <th class="py-3 px-4 font-bold text-xs uppercase text-zinc-500 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($programs as $prog)
                            @php
                                $isProgUnlocked = in_array($prog->id, $unlockedProgramIds);
                            @endphp
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" variant="subtle" class="font-mono font-bold">{{ $prog->acronym }}</flux:badge>
                                        <span class="font-bold">{{ $prog->name }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $prog->department?->name ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    @if ($isProgUnlocked)
                                        <flux:badge color="emerald" size="sm" class="uppercase font-bold">
                                            <flux:icon.lock-open class="size-3 mr-1" />
                                            {{ __('Unlocked') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm" class="uppercase font-bold">
                                            <flux:icon.lock-closed class="size-3 mr-1" />
                                            {{ __('Locked (Default)') }}
                                        </flux:badge>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <flux:button
                                        size="sm"
                                        variant="{{ $isProgUnlocked ? 'filled' : 'primary' }}"
                                        wire:click="toggleProgramUnlock({{ $prog->id }})"
                                        icon="{{ $isProgUnlocked ? 'lock-closed' : 'lock-open' }}"
                                    >
                                        {{ $isProgUnlocked ? __('Lock') : __('Unlock') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-zinc-400 italic">
                                    {{ __('No programs found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @else
        <flux:card class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-400">
            <flux:icon.key class="size-12 mx-auto mb-4 text-zinc-300" />
            <h3 class="font-medium text-zinc-900 dark:text-white mb-1">{{ __('Select Session and Semester') }}</h3>
            <p class="text-sm">{{ __('Please select an academic session and semester above to manage CBT PIN access controls.') }}</p>
        </flux:card>
    @endif
</div>
