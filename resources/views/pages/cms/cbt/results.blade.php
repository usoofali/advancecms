<?php

use App\Models\CbtResultStaging;
use App\Models\Result;
use App\Services\GradingService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Examination Results Review')] class extends Component {
    use WithPagination;

    public $selectedResultId = null;
    public bool $showReviewModal = false;
    public string $filter_exam_id = '';
    
    #[\Livewire\Attributes\Url]
    public string $filter_status = 'pending';
    
    public string $search = '';
    public array $selectedResults = [];
    public bool $selectAll = false;
    public bool $showApproveAllModal = false;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->selectedResults = [];
        $this->selectAll = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedResults = [];
        $this->selectAll = false;
    }

    public function mount(): void
    {
        Gate::authorize('cbt_results.view');
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedResults = $this->getResultsQuery()->pluck('id')->mapWithKeys(fn($id) => [$id => true])->toArray();
        } else {
            $this->selectedResults = [];
        }
    }

    public function updatedFilterExamId(): void
    {
        $this->resetPage();
        $this->selectedResults = [];
        $this->selectAll = false;
    }

    public function review($id): void
    {
        Gate::authorize('cbt_results.review');
        $this->selectedResultId = $id;
        $this->showReviewModal = true;
    }

    public function approve($id): void
    {
        Gate::authorize('cbt_results.approve');
        $this->processApproval($id);
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Result Finalized and committed to academic records.',
        ]);
        
        if ($this->selectedResultId == $id) {
            $this->showReviewModal = false;
        }
    }

    public function approveAllForExam(): void
    {
        Gate::authorize('cbt_results.mass_action');
        if (!$this->filter_exam_id) return;

        $pendingResults = CbtResultStaging::where('cbt_exam_id', $this->filter_exam_id)
            ->where('status', 'pending')
            ->get();

        if ($pendingResults->isEmpty()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'No pending results found for this exam.']);
            return;
        }

        foreach ($pendingResults as $staging) {
            $this->processApproval($staging->id);
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Batch Success: ' . $pendingResults->count() . ' results approved for this examination.',
        ]);

        $this->showApproveAllModal = false;
    }

    public function massApprove(): void
    {
        Gate::authorize('cbt_results.mass_action');
        $ids = array_keys(array_filter($this->selectedResults));
        
        if (empty($ids)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No results selected.']);
            return;
        }

        foreach ($ids as $id) {
            $this->processApproval($id);
        }

        $this->selectedResults = [];
        $this->selectAll = false;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => count($ids) . ' results have been finalized and approved.',
        ]);
    }

    protected function processApproval($id): void
    {
        $staging = CbtResultStaging::findOrFail($id);
        if ($staging->status === 'approved') return;

        DB::transaction(function () use ($staging) {
            $exam = $staging->exam;
            
            // Always use firstOrNew — for resits this finds the existing record
            $result = Result::firstOrNew([
                'student_id' => $staging->student_id,
                'course_id' => $exam->course_id,
                'academic_session_id' => $exam->academic_session_id,
                'semester_id' => $exam->semester_id,
            ]);

            $result->institution_id = $exam->institution_id;
            
            $weightedExamScore = ($staging->score_raw / max(1, $exam->total_questions)) * 70;
            $result->exam_score = max($result->exam_score, round($weightedExamScore, 2));
            $result->total_score = ($result->ca_score ?? 0) + $result->exam_score;
            
            $gradingService = new GradingService();
            $gradingService->grade($result);
            $result->save();

            $staging->update([
                'status' => 'approved',
                'processed_at' => now(),
            ]);
        });
    }

    public function reject($id): void
    {
        Gate::authorize('cbt_results.reject');
        CbtResultStaging::findOrFail($id)->update(['status' => 'rejected']);
        $this->showReviewModal = false;
        $this->dispatch('notify', [
            'type' => 'danger',
            'message' => 'Result Discarded: The staged record was marked as rejected.',
        ]);
    }

    protected function getResultsQuery()
    {
        $user = auth()->user();
        $instId = $user->institution_id;
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $query = CbtResultStaging::whereHas('exam', function ($q) use ($instId, $isRestrictedLecturer, $user, $scopedDeptIds) {
            $q->where('institution_id', $instId)
                ->when($isRestrictedLecturer, function ($sq) use ($user) {
                    $sq->whereIn('course_id', function ($sub) use ($user) {
                        $sub->select('course_id')
                            ->from('course_allocations')
                            ->where('user_id', $user->id);
                    });
                })
                ->when(!empty($scopedDeptIds), function ($sq) use ($scopedDeptIds) {
                    $sq->whereHas('course', function ($cq) use ($scopedDeptIds) {
                        $cq->whereIn('department_id', $scopedDeptIds);
                    });
                });
        });

        if ($this->filter_status) {
            $query->where('status', $this->filter_status);
        }

        if ($this->filter_exam_id) {
            $query->where('cbt_exam_id', $this->filter_exam_id);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('student', function ($sq) {
                    $sq->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('matric_number', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('exam', function ($eq) {
                    $eq->where('title', 'like', '%' . $this->search . '%')
                        ->orWhereHas('course', function ($cq) {
                            $cq->where('course_code', 'like', '%' . $this->search . '%');
                        });
                });
            });
        }

        return $query;
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $user->institution_id;
        $stagingQuery = $this->getResultsQuery();
        
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        // Analytics
        $totalResults = (clone $stagingQuery)->count();
        $avgScore = (clone $stagingQuery)->avg('score_percent') ?? 0;
        $passCount = (clone $stagingQuery)->where('score_percent', '>=', 40)->count();

        // Selected result with full question context for digital script
        $selectedResult = null;
        if ($this->selectedResultId) {
            $selectedResult = CbtResultStaging::with(['student', 'exam.course'])->find($this->selectedResultId);
            if ($selectedResult && is_array($selectedResult->responses) && !empty($selectedResult->responses)) {
                // Responses now contain inline question data from the lab
                $selectedResult->script = collect($selectedResult->responses)->map(function($resp) {
                    return [
                        'question_text'  => $resp['question_text'] ?? $resp['question'] ?? 'Question text unavailable',
                        'chosen_option'  => $resp['chosen_option'] ?? $resp['chosen_option_id'] ?? null,
                        'chosen_text'    => $resp['chosen_text'] ?? null,
                        'correct_option' => $resp['correct_option'] ?? null,
                        'correct_text'   => $resp['correct_text'] ?? null,
                        'is_correct'     => $resp['is_correct'] ?? false,
                        // Legacy format support (CbtQuestion-based)
                        'question_id'    => $resp['question_id'] ?? null,
                    ];
                });
            }

            // For resits, fetch the previous attempt's score for comparison
            if ($selectedResult && $selectedResult->attempt_type === 'resit') {
                $selectedResult->previousAttempt = CbtResultStaging::where('cbt_exam_id', $selectedResult->cbt_exam_id)
                    ->where('student_id', $selectedResult->student_id)
                    ->where('attempt_number', '<', $selectedResult->attempt_number)
                    ->orderByDesc('attempt_number')
                    ->first();
            }
        }

        return [
            'results' => $stagingQuery->with(['student', 'exam.course'])->latest()->paginate(20),
            'exams' => \App\Models\CbtExam::where('institution_id', $instId)
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
                ->with('course')->get(),
            'stats' => [
                'total' => $totalResults,
                'avg' => round($avgScore, 1),
                'pass_rate' => $totalResults > 0 ? round(($passCount / $totalResults) * 100, 1) : 0,
                'pending' => (clone $stagingQuery)->where('status', 'pending')->count(),
            ],
            'selectedResult' => $selectedResult,
            'currentExam' => $this->filter_exam_id ? \App\Models\CbtExam::find($this->filter_exam_id) : null,
        ];
    }
}; ?>

<div class="p-6">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 no-print">
        <div>
            <flux:heading size="xl">{{ __('Results & Analytics') }}</flux:heading>
            <flux:subheading>{{ __('Select an examination session to review and finalize student scores.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">

            <flux:button icon="printer" variant="subtle" onclick="window.print()">{{ __('Print Report') }}</flux:button>
            
            @if($filter_exam_id)
                @can('cbt_results.mass_action')
                    <flux:button variant="primary" icon="check-badge" wire:click="$set('showApproveAllModal', true)" :disabled="$stats['pending'] == 0">
                        {{ __('Approve All for this Exam') }}
                    </flux:button>
                @endcan
            @endif
        </div>
    </div>
    <div class="flex flex-col md:flex-row mb-8 gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Search student, matric, exam or course...') }}" class="w-full md:w-[250px]" />

        <div class="w-full md:w-[200px]">
            <flux:select wire:model.live="filter_status">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="rejected">{{ __('Rejected') }}</option>
            </flux:select>
        </div>

        <flux:select wire:model.live="filter_exam_id" placeholder="{{ __('Choose Examination...') }}" class="w-full md:min-w-[250px]">
            <flux:select.option value="">{{ __('--- All Exams ---') }}</flux:select.option>
            @foreach($exams as $exam)
                <flux:select.option :value="$exam->id">{{ $exam->course->course_code }} - {{ $exam->title }} ({{ $exam->exam_date->format('M d, Y') }})</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- High-Density Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8 no-print">
        <flux:card class="p-4 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon icon="users" class="size-5 text-blue-600" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase font-bold text-zinc-500">{{ __('Total Scripts') }}</flux:text>
                    <div class="text-xl font-black">{{ $stats['total'] }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <flux:icon icon="chart-bar" class="size-5 text-green-600" />
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-end mb-1">
                        <flux:text size="xs" class="uppercase font-bold text-zinc-500">{{ __('Pass Rate') }}</flux:text>
                        <span class="text-xs font-bold text-green-600">{{ $stats['pass_rate'] }}%</span>
                    </div>
                    <div class="h-1 w-full bg-zinc-100 dark:bg-zinc-700 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500" style="width: {{ $stats['pass_rate'] }}%"></div>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon icon="variable" class="size-5 text-purple-600" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase font-bold text-zinc-500">{{ __('Average Score') }}</flux:text>
                    <div class="text-xl font-black">{{ $stats['avg'] }}%</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
                    <flux:icon icon="clock" class="size-5 text-orange-600" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase font-bold text-zinc-500">{{ __('Awaiting Review') }}</flux:text>
                    <div class="text-xl font-black text-orange-600">{{ $stats['pending'] }}</div>
                </div>
            </div>
        </flux:card>
    </div>
    <div class="no-print">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Staged Result Records') }}</flux:heading>
        </div>
        
        <flux:table :paginate="$results">
            <flux:table.columns>
                @can('cbt_results.mass_action')
                    <flux:table.column class="w-10">
                        <flux:checkbox wire:model.live="selectAll" />
                    </flux:table.column>
                @endcan
                <flux:table.column>{{ __('Student Details') }}</flux:table.column>
                <flux:table.column>{{ __('Examination') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Exam Score (70%)') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Synced At') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($results as $result)
                    <flux:table.row :key="$result->id">
                        @can('cbt_results.mass_action')
                            <flux:table.cell>
                                <flux:checkbox wire:model.live="selectedResults.{{ $result->id }}" />
                            </flux:table.cell>
                        @endcan
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center font-bold text-zinc-500 text-xs">
                                    {{ substr($result->student->first_name, 0, 1) }}{{ substr($result->student->last_name, 0, 1) }}
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-zinc-900 dark:text-white">{{ $result->student->full_name }}</span>
                                        @if($result->attempt_type === 'resit')
                                            <flux:badge color="purple" size="sm" variant="pill">{{ __('Resit') }} #{{ $result->attempt_number }}</flux:badge>
                                        @endif
                                    </div>
                                    <span class="text-[10px] font-mono text-zinc-400">{{ $result->student->matric_number }}</span>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium">{{ $result->exam->title }}</span>
                                <span class="text-[10px] font-bold text-blue-500 uppercase tracking-tighter">{{ $result->exam->course->course_code }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            @php 
                                $weighted = ($result->score_raw / max(1, $result->exam->total_questions)) * 70;
                                $isPass = $result->score_percent >= 40; 
                            @endphp
                            <div class="flex flex-col items-center">
                                <span class="text-lg font-black {{ $isPass ? 'text-green-600' : 'text-red-600' }}">{{ number_format($weighted, 2) }}</span>
                                <span class="text-[9px] text-zinc-400 font-mono">{{ $result->score_raw }} / {{ $result->exam->total_questions }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="match($result->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'orange' }" size="sm" variant="pill">
                                {{ ucfirst($result->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="text-xs text-zinc-900 dark:text-white">{{ $result->synced_at->format('M d, Y') }}</span>
                                <span class="text-[10px] text-zinc-400">{{ $result->synced_at->diffForHumans() }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1 justify-end">
                                @can('cbt_results.review')
                                    <flux:button variant="ghost" size="sm" icon="eye" wire:click="review({{ $result->id }})" >
                                        {{ __('View') }}
                                    </flux:button>
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Print Layout (Institutional Standard) --}}
    <div class="hidden print:block print:p-0 text-black">
        @php
            $printInst = auth()->user()->institution;
        @endphp
        
        <div class="flex items-center gap-6 border-b-4 border-black pb-6 mb-8">
            @if ($printInst && $printInst->logo_path)
                <img src="{{ asset('storage/'.$printInst->logo_path) }}" class="h-20 w-20 object-contain" alt="Logo">
            @else
                <div class="h-20 w-20 bg-zinc-100 border border-zinc-300 flex items-center justify-center">
                    <span class="text-[10px] uppercase font-bold text-zinc-400">LOGO</span>
                </div>
            @endif
            <div class="flex-1">
                <h1 class="text-3xl font-black uppercase tracking-tighter leading-none mb-1">
                    {{ $printInst?->name ?? __('Academic Institution') }}
                </h1>
                <p class="text-sm font-bold uppercase text-zinc-600 tracking-widest">
                    {{ $printInst?->address ?? __('Official Examination Records') }}
                </p>
                <div class="mt-4 inline-block px-3 py-1 bg-black text-white text-[10px] font-black uppercase tracking-[0.2em]">
                    {{ __('CBT Score Report') }}
                </div>
            </div>
            <div class="text-right">
                <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-1">{{ __('Generation Timestamp') }}</div>
                <div class="text-xs font-mono font-bold">{{ now()->format('d/m/Y H:i:s') }}</div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8">
            <div class="space-y-1">
                <div class="flex gap-2 text-xs">
                    <span class="font-bold uppercase text-zinc-500 w-24 shrink-0">{{ __('Examination:') }}</span>
                    <span class="font-black text-zinc-900 italic">{{ $currentExam?->title ?? __('General Session') }}</span>
                </div>
                <div class="flex gap-2 text-xs">
                    <span class="font-bold uppercase text-zinc-500 w-24 shrink-0">{{ __('Course:') }}</span>
                    <span class="font-bold text-zinc-900">{{ $currentExam?->course->course_code ?? '---' }} &bull; {{ $currentExam?->course->title ?? '---' }}</span>
                </div>
                <div class="flex gap-2 text-xs">
                    <span class="font-bold uppercase text-zinc-500 w-24 shrink-0">{{ __('Exam Date:') }}</span>
                    <span class="font-mono">{{ $currentExam?->exam_date->format('l, F d, Y') ?? '---' }}</span>
                </div>
            </div>
            <div class="flex justify-end">
                <div class="border-2 border-black p-3 inline-block min-w-[200px]">
                    <div class="text-[9px] font-black uppercase tracking-widest border-b border-black pb-1 mb-2">{{ __('Performance Summary') }}</div>
                    <div class="flex justify-between text-xs font-bold mb-1">
                        <span>{{ __('Scripts:') }}</span>
                        <span>{{ $stats['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-green-700">
                        <span>{{ __('Pass Rate:') }}</span>
                        <span>{{ $stats['pass_rate'] }}%</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-purple-700">
                        <span>{{ __('Avg Score:') }}</span>
                        <span>{{ $stats['avg'] }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <table class="w-full border-collapse border-2 border-black">
            <thead>
                <tr class="bg-zinc-100 border-b-2 border-black">
                    <th class="border border-zinc-300 px-4 py-2 text-left text-[10px] uppercase font-black">{{ __('S/N') }}</th>
                    <th class="border border-zinc-300 px-4 py-2 text-left text-[10px] uppercase font-black">{{ __('Matric Number') }}</th>
                    <th class="border border-zinc-300 px-4 py-2 text-left text-[10px] uppercase font-black">{{ __('Full Name of Student') }}</th>
                    <th class="border border-zinc-300 px-4 py-2 text-center text-[10px] uppercase font-black">{{ __('Raw Score') }}</th>
                    <th class="border border-zinc-300 px-4 py-2 text-center text-[10px] uppercase font-black bg-zinc-200">{{ __('Weighted (70%)') }}</th>
                    <th class="border border-zinc-300 px-4 py-2 text-center text-[10px] uppercase font-black">{{ __('Remark') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $index => $result)
                    <tr class="border-b border-zinc-200">
                        <td class="border-r border-zinc-300 px-4 py-1 text-[11px]">{{ $index + 1 }}</td>
                        <td class="border-r border-zinc-300 px-4 py-1 text-[11px] font-mono">{{ $result->student->matric_number }}</td>
                        <td class="border-r border-zinc-300 px-4 py-1 text-[11px] font-bold uppercase">{{ $result->student->full_name }}</td>
                        <td class="border-r border-zinc-300 px-4 py-1 text-[11px] text-center italic text-zinc-500">{{ $result->score_raw }} / {{ $result->exam->total_questions }}</td>
                        <td class="border-r border-zinc-300 px-4 py-1 text-xs text-center font-black bg-zinc-50">
                            {{ number_format(($result->score_raw / max(1, $result->exam->total_questions)) * 70, 2) }}
                        </td>
                        <td class="px-4 py-1 text-[9px] text-center font-black uppercase">
                            {{ $result->score_percent >= 40 ? 'PASS' : 'FAIL' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="mt-24 flex justify-between px-4">
            <div class="w-64 border-t-2 border-black pt-2 text-center">
                <div class="text-[10px] font-black uppercase mb-1">{{ __('Course Lecturer') }}</div>
                <div class="text-[9px] text-zinc-400">{{ __('Signature / Date') }}</div>
            </div>
            <div class="w-64 border-t-2 border-black pt-2 text-center">
                <div class="text-[10px] font-black uppercase mb-1">{{ __('Head of Department') }}</div>
                <div class="text-[9px] text-zinc-400">{{ __('Signature / Date') }}</div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .p-6 { padding: 0 !important; }
        }
    </style>
    {{-- Digital Script Review Modal --}}
    @if($selectedResult)
    <flux:modal wire:model="showReviewModal" class="w-full max-w-5xl h-[90vh] flex flex-col !p-0">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-6 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <div class="size-14 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-xl font-black text-zinc-400 border border-zinc-200 dark:border-zinc-700 shrink-0">
                    {{ round($selectedResult->score_percent) }}%
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" class="truncate">{{ __('Examination Script Audit') }}</flux:heading>
                    <flux:subheading class="truncate">{{ $selectedResult->student->full_name }}</flux:subheading>
                </div>
            </div>
            <div class="flex gap-2 w-full sm:w-auto shrink-0">
                @if($selectedResult->status === 'pending')
                    @can('cbt_results.reject')
                        <flux:button variant="danger" icon="trash" wire:click="reject({{ $selectedResult->id }})" class="flex-1 sm:flex-none">{{ __('Discard') }}</flux:button>
                    @endcan
                    @can('cbt_results.approve')
                        <flux:button variant="primary" icon="check-circle" wire:click="approve({{ $selectedResult->id }})" class="flex-1 sm:flex-none">{{ __('Approve') }}</flux:button>
                    @endcan
                @else
                    <flux:badge color="zinc" size="lg" icon="check-badge" class="w-full sm:w-auto justify-center">{{ __('Processed') }}</flux:badge>
                @endif
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-zinc-50 dark:bg-zinc-900/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <flux:card class="bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:text size="xs" class="uppercase font-bold text-zinc-400 mb-2">{{ __('Score Analysis') }}</flux:text>
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Correct Answers') }}</span>
                            <span class="font-black text-green-600">{{ $selectedResult->score_raw }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Wrong Answers') }}</span>
                            <span class="font-black text-red-600">{{ $selectedResult->exam->total_questions - $selectedResult->score_raw }}</span>
                        </div>
                    </div>
                </flux:card>
                <flux:card class="bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:text size="xs" class="uppercase font-bold text-zinc-400 mb-2">{{ __('Submission Metadata') }}</flux:text>
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Time') }}</span>
                            <span class="font-bold text-zinc-900 dark:text-white">{{ $selectedResult->created_at->format('H:i:s') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Sync Lag') }}</span>
                            <span class="font-bold text-zinc-400">{{ $selectedResult->synced_at->diffForHumans($selectedResult->created_at, true) }}</span>
                        </div>
                    </div>
                </flux:card>
                <flux:card class="bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 p-4 sm:col-span-2 lg:col-span-1">
                    <flux:text size="xs" class="uppercase font-bold text-zinc-400 mb-2">{{ __('Security Context') }}</flux:text>
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Attempt') }}</span>
                            <span class="font-black text-zinc-900 dark:text-white">#{{ $selectedResult->attempt_number }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-zinc-500">{{ __('Source IP') }}</span>
                            <span class="font-mono text-[10px] font-bold text-zinc-400">{{ $selectedResult->ip_address ?? '127.0.0.1' }}</span>
                        </div>
                    </div>
                </flux:card>
            </div>

            {{-- Previous Attempt Comparison (Resit only) --}}
            @if($selectedResult->attempt_type === 'resit' && isset($selectedResult->previousAttempt))
                <flux:card class="bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800 p-4">
                    <div class="flex items-center gap-3 mb-2">
                        <flux:icon icon="arrow-path" class="size-5 text-purple-600" />
                        <flux:text size="xs" class="uppercase font-black text-purple-600 tracking-widest">{{ __('Resit Comparison') }}</flux:text>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Previous Attempt') }} (#{{ $selectedResult->previousAttempt->attempt_number }})</div>
                            @php
                                $prevWeighted = ($selectedResult->previousAttempt->score_raw / max(1, $selectedResult->exam->total_questions)) * 70;
                            @endphp
                            <div class="text-xl font-black text-red-600">{{ number_format($prevWeighted, 2) }}</div>
                            <div class="text-[9px] text-zinc-400 font-mono">{{ $selectedResult->previousAttempt->score_raw }} / {{ $selectedResult->exam->total_questions }} correct</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-zinc-400 mb-1">{{ __('Current Resit') }} (#{{ $selectedResult->attempt_number }})</div>
                            @php
                                $currWeighted = ($selectedResult->score_raw / max(1, $selectedResult->exam->total_questions)) * 70;
                            @endphp
                            <div class="text-xl font-black text-green-600">{{ number_format($currWeighted, 2) }}</div>
                            <div class="text-[9px] text-zinc-400 font-mono">{{ $selectedResult->score_raw }} / {{ $selectedResult->exam->total_questions }} correct</div>
                        </div>
                    </div>
                </flux:card>
            @endif

            <flux:separator text="{{ __('Question Breakdown') }}" />

            <div class="space-y-4 pb-12">
                @if(isset($selectedResult->script) && $selectedResult->script->isNotEmpty())
                    @foreach($selectedResult->script as $idx => $item)
                        <div class="p-5 bg-white dark:bg-zinc-800 rounded-2xl border {{ $item['is_correct'] ? 'border-green-200 dark:border-green-900/30' : 'border-red-200 dark:border-red-900/30' }}">
                            <div class="flex items-start gap-4">
                                <div class="size-8 rounded-full {{ $item['is_correct'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }} flex items-center justify-center font-black text-sm shrink-0">
                                    {{ $idx + 1 }}
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-zinc-900 dark:text-white mb-3 leading-relaxed">
                                        {{ $item['question_text'] }}
                                    </div>
                                    
                                    <div class="flex flex-col gap-2 text-xs">
                                        <div class="flex items-start gap-2">
                                            <span @class(['font-black uppercase tracking-widest text-[9px] mt-1 shrink-0', 'text-green-600' => $item['is_correct'], 'text-red-600' => !$item['is_correct']])>
                                                {{ __('Student:') }}
                                            </span>
                                            <span @class(['font-medium leading-relaxed', 'text-green-700' => $item['is_correct'], 'text-red-700' => !$item['is_correct']])>
                                                {{ $item['chosen_text'] ?? ('Option ' . $item['chosen_option']) }}
                                            </span>
                                        </div>
                                        
                                        @if(!$item['is_correct'] && $item['correct_text'])
                                            <div class="flex items-start gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-700/50">
                                                <span class="font-black uppercase tracking-widest text-[9px] mt-1 text-green-600 shrink-0">
                                                    {{ __('Correct:') }}
                                                </span>
                                                <span class="font-bold text-green-700 leading-relaxed">
                                                    {{ $item['correct_text'] }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="p-12 text-center text-zinc-500 italic bg-zinc-50 dark:bg-zinc-900 rounded-3xl">
                        {{ __('Response breakdown is unavailable for this record.') }}
                    </div>
                @endif
            </div>
        </div>
    </flux:modal>
    @endif

    {{-- Batch Approval Confirmation Modal --}}
    <flux:modal name="approve-all-confirmation" wire:model="showApproveAllModal" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Finalize Batch Results') }}</flux:heading>
                <flux:subheading>{{ __('You are about to approve and commit all pending results for this examination.') }}</flux:subheading>
            </div>

            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider">{{ __('Examination') }}</span>
                    <flux:badge color="blue" size="sm">{{ $currentExam?->course->course_code }}</flux:badge>
                </div>
                <div class="text-sm font-bold text-zinc-900 dark:text-white mb-4">{{ $currentExam?->title }}</div>
                
                <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider">{{ __('Pending Records') }}</span>
                    <span class="text-lg font-black text-orange-500">{{ $stats['pending'] }}</span>
                </div>
            </div>

            <flux:callout variant="danger">
                {{ __('This action will calculate weighted scores (70%) and finalize academic records for all students in this batch. This cannot be undone.') }}
            </flux:callout>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showApproveAllModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="approveAllForExam" x-on:click="$slideover.close()">{{ __('Yes, Finalize All') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
