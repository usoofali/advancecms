<?php

use App\Models\CbtExam;
use App\Models\CbtQuestion;
use App\Models\CbtOption;
use App\Models\Department;
use App\Models\Course;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('CBT Questions Bank')] class extends Component {
    use WithPagination, WithFileUploads;

    public $selectedExamId = '';
    public bool $showModal = false;
    public bool $showImportModal = false;
    public $editingId = null;
    
    public string $question_text = '';
    public string $type = 'single';
    public int $correct_index = 0;
    public array $options = [];

    // CSV Import
    public $csvFile;

    public function mount(): void
    {
        Gate::authorize('cbt_questions.view');
        $this->resetOptions();
    }

    public function resetOptions(): void
    {
        $this->correct_index = 0;
        $this->options = [
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
        ];
        $this->options[$this->correct_index]['is_correct'] = true;
    }

    public function addOption(): void
    {
        $this->options[] = ['text' => '', 'is_correct' => false];
    }

    public function removeOption($index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function save(): void
    {
        if ($this->editingId) {
            Gate::authorize('cbt_questions.edit');
        } else {
            Gate::authorize('cbt_questions.create');
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
            'selectedExamId' => [
                'required',
                'exists:cbt_exams,id',
                function ($attribute, $value, $fail) use ($isRestrictedLecturer, $user, $scopedDeptIds) {
                    $exam = CbtExam::find($value);
                    if ($exam) {
                        if ($isRestrictedLecturer) {
                            $isAllocated = DB::table('course_allocations')
                                ->where('user_id', $user->id)
                                ->where('course_id', $exam->course_id)
                                ->exists();
                            if (!$isAllocated) {
                                $fail('You do not have access to this examination.');
                            }
                        } elseif (!empty($scopedDeptIds)) {
                            $course = Course::find($exam->course_id);
                            if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                                $fail('This examination belongs to a course outside your department.');
                            }
                        }
                    }
                }
            ],
            'question_text' => 'required|string',
            'options.*.text' => 'required|string',
        ]);

        DB::transaction(function () {
            $data = [
                'cbt_exam_id' => $this->selectedExamId,
                'question_text' => $this->question_text,
                'type' => $this->type,
                'marks' => 1,
            ];

            if ($this->editingId) {
                $question = CbtQuestion::findOrFail($this->editingId);
                $question->update($data);
                $question->options()->delete();
            } else {
                $data['uuid'] = (string) Str::uuid();
                $question = CbtQuestion::create($data);
            }

            foreach ($this->options as $index => $optionData) {
                $question->options()->create([
                    'uuid' => (string) Str::uuid(),
                    'option_text' => $optionData['text'],
                    'is_correct' => ($index === (int) $this->correct_index),
                ]);
            }
        });

        $this->showModal = false;
        $this->reset(['editingId', 'question_text']);
        $this->resetOptions();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Question saved successfully.',
        ]);
    }

    public function importCsv(): void
    {
        Gate::authorize('cbt_questions.import');
        
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
            'selectedExamId' => [
                'required',
                'exists:cbt_exams,id',
                function ($attribute, $value, $fail) use ($isRestrictedLecturer, $user, $scopedDeptIds) {
                    $exam = CbtExam::find($value);
                    if ($exam) {
                        if ($isRestrictedLecturer) {
                            $isAllocated = DB::table('course_allocations')
                                ->where('user_id', $user->id)
                                ->where('course_id', $exam->course_id)
                                ->exists();
                            if (!$isAllocated) {
                                $fail('You do not have access to this examination.');
                            }
                        } elseif (!empty($scopedDeptIds)) {
                            $course = Course::find($exam->course_id);
                            if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                                $fail('This examination belongs to a course outside your department.');
                            }
                        }
                    }
                }
            ],
            'csvFile' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $path = $this->csvFile->getRealPath();
        $file = fopen($path, 'r');
        
        // Skip header
        fgetcsv($file);

        $importCount = 0;
        
        DB::transaction(function () use ($file, &$importCount) {
            while (($row = fgetcsv($file)) !== false) {
                // Expected: Question Text, Opt1, Opt2, Opt3, Opt4, Correct Index (1-4), Marks
                if (count($row) < 6) continue;

                $qText = $row[0];
                $opts = [$row[1], $row[2], $row[3], $row[4]];
                $correctIdx = (int)$row[5] - 1; // Convert 1-4 to 0-3

                $question = CbtQuestion::create([
                    'uuid' => (string) Str::uuid(),
                    'cbt_exam_id' => $this->selectedExamId,
                    'question_text' => $qText,
                    'type' => 'single',
                    'marks' => 1,
                ]);

                foreach ($opts as $idx => $optText) {
                    if (empty($optText)) continue;
                    $question->options()->create([
                        'uuid' => (string) Str::uuid(),
                        'option_text' => $optText,
                        'is_correct' => ($idx === $correctIdx),
                    ]);
                }
                $importCount++;
            }
        });

        fclose($file);
        $this->showImportModal = false;
        $this->reset('csvFile');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Successfully imported {$importCount} questions.",
        ]);
    }

    public function edit($id): void
    {
        Gate::authorize('cbt_questions.edit');

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
            $isAllocated = DB::table('course_allocations')
                ->where('user_id', $user->id)
                ->where('course_id', function ($query) use ($id) {
                    $query->select('cbt_exams.course_id')
                        ->from('cbt_exams')
                        ->join('cbt_questions', 'cbt_questions.cbt_exam_id', '=', 'cbt_exams.id')
                        ->where('cbt_questions.id', $id)
                        ->limit(1);
                })
                ->exists();
            if (!$isAllocated) {
                abort(403, 'Unauthorized action.');
            }
        } elseif (!empty($scopedDeptIds)) {
            $question = CbtQuestion::find($id);
            if ($question) {
                $exam = CbtExam::find($question->cbt_exam_id);
                if ($exam) {
                    $course = Course::find($exam->course_id);
                    if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                        abort(403, 'Unauthorized action.');
                    }
                }
            }
        }

        $question = CbtQuestion::with('options')->findOrFail($id);
        $this->editingId = $question->id;
        $this->question_text = $question->question_text;
        $this->type = $question->type;
        
        $this->options = $question->options->map(fn($o) => [
            'text' => $o->option_text,
            'is_correct' => (bool) $o->is_correct,
        ])->toArray();

        foreach ($this->options as $idx => $opt) {
            if ($opt['is_correct']) {
                $this->correct_index = $idx;
                break;
            }
        }
        
        $this->showModal = true;
    }

    public function delete($id): void
    {
        Gate::authorize('cbt_questions.delete');

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
            $isAllocated = DB::table('course_allocations')
                ->where('user_id', $user->id)
                ->where('course_id', function ($query) use ($id) {
                    $query->select('cbt_exams.course_id')
                        ->from('cbt_exams')
                        ->join('cbt_questions', 'cbt_questions.cbt_exam_id', '=', 'cbt_exams.id')
                        ->where('cbt_questions.id', $id)
                        ->limit(1);
                })
                ->exists();
            if (!$isAllocated) {
                abort(403, 'Unauthorized action.');
            }
        } elseif (!empty($scopedDeptIds)) {
            $question = CbtQuestion::find($id);
            if ($question) {
                $exam = CbtExam::find($question->cbt_exam_id);
                if ($exam) {
                    $course = Course::find($exam->course_id);
                    if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                        abort(403, 'Unauthorized action.');
                    }
                }
            }
        }

        CbtQuestion::findOrFail($id)->delete();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Question deleted successfully.',
        ]);
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

        $currentExam = $this->selectedExamId ? CbtExam::find($this->selectedExamId) : null;
        $addedCount = $this->selectedExamId ? CbtQuestion::where('cbt_exam_id', $this->selectedExamId)->count() : 0;

        return [
            'exams' => CbtExam::where('institution_id', $instId)
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
                ->get(),
            'stats' => [
                'total_added' => $addedCount,
                'required' => $currentExam?->total_questions ?? 0,
                'is_ready' => $currentExam && $addedCount >= $currentExam->total_questions,
            ],
            'questions' => $this->selectedExamId 
                ? CbtQuestion::where('cbt_exam_id', $this->selectedExamId)
                    ->with('options')
                    ->latest()
                    ->paginate(10)
                : collect(),
        ];
    }

}; ?>

<div class="p-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <flux:heading size="xl">{{ __('Questions Bank') }}</flux:heading>
            <flux:subheading>{{ __('Manage question banks and answers for institutional CBT exams.') }}</flux:subheading>
        </div>
        @if($selectedExamId)
            <div class="flex flex-wrap items-center gap-2">
                @can('cbt_questions.import')
                    <flux:button icon="arrow-up-tray" variant="subtle" wire:click="$set('showImportModal', true)" class="flex-1 sm:flex-none">{{ __('Import CSV') }}</flux:button>
                @endcan
                @can('cbt_questions.create')
                    <flux:button variant="primary" icon="plus" wire:click="$set('showModal', true)" class="flex-1 sm:flex-none">{{ __('New Question') }}</flux:button>
                @endcan
            </div>
        @endif
    </div>

    <div class="mb-8">
        <flux:card class="p-0 overflow-hidden">
            <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800">
                <flux:select label="{{ __('Target Examination') }}" wire:model.live="selectedExamId">
                    <option value="">{{ __('-- Choose an exam to manage --') }}</option>
                    @foreach ($exams as $exam)
                        <option value="{{ $exam->id }}">{{ $exam->title }} ({{ $exam->course->course_code }})</option>
                    @endforeach
                </flux:select>
            </div>

            @if($selectedExamId)
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-zinc-200 dark:divide-zinc-800">
                    <div class="p-6">
                        <flux:text size="sm" class="uppercase tracking-wider font-bold text-zinc-500 mb-1">{{ __('Questions Added') }}</flux:text>
                        <div class="flex items-end gap-2">
                            <span class="text-3xl font-black">{{ $stats['total_added'] }}</span>
                            <span class="text-zinc-400 mb-1">/ {{ $stats['required'] }}</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <flux:text size="sm" class="uppercase tracking-wider font-bold text-zinc-500 mb-1">{{ __('Readiness') }}</flux:text>
                        <div>
                            @if($stats['is_ready'])
                                <flux:badge color="success" size="lg" icon="check-circle">{{ __('Bank Ready') }}</flux:badge>
                            @else
                                <flux:badge color="orange" size="lg" icon="exclamation-triangle">{{ __('Incomplete') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="p-6">
                        <flux:text size="sm" class="uppercase tracking-wider font-bold text-zinc-500 mb-1">{{ __('Completion') }}</flux:text>
                        @php $progress = $stats['required'] > 0 ? min(100, ($stats['total_added'] / $stats['required']) * 100) : 0; @endphp
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full mt-3 overflow-hidden">
                            <div class="bg-blue-600 h-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                        </div>
                        <flux:text size="xs" class="mt-1 text-zinc-500">{{ round($progress) }}% {{ __('of minimum bank requirement') }}</flux:text>
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    @if($selectedExamId)
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Question List') }}</flux:heading>
            </div>
            
            <flux:table :paginate="$questions">
                <flux:table.columns>
                    <flux:table.column>{{ __('Question Content') }}</flux:table.column>
                    <flux:table.column>{{ __('Options & Answers') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($questions as $q)
                        <flux:table.row :key="$q->id">
                            <flux:table.cell class="max-w-md">
                                <div class="text-zinc-900 dark:text-white font-medium mb-1 line-clamp-2" title="{{ strip_tags($q->question_text) }}">
                                    {{ strip_tags($q->question_text) }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="grid grid-cols-2 gap-1 min-w-[300px]">
                                    @foreach($q->options as $option)
                                        <div class="flex items-center gap-2 text-xs">
                                            @if($option->is_correct)
                                                <flux:icon icon="check-circle" variant="mini" class="size-3 text-green-500" />
                                            @else
                                                <div class="size-3 rounded-full border-2 border-zinc-300"></div>
                                            @endif
                                            <span class="{{ $option->is_correct ? 'font-bold text-zinc-900 dark:text-white' : 'text-zinc-500' }} truncate max-w-[120px]">
                                                {{ $option->option_text }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    @can('cbt_questions.edit')
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="edit({{ $q->id }})" />
                                    @endcan
                                    @can('cbt_questions.delete')
                                        <flux:button variant="ghost" size="sm" icon="trash" color="red" wire:click="delete({{ $q->id }})" wire:confirm="{{ __('Delete this question?') }}" />
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-20 text-zinc-500">
                                <flux:icon icon="document-magnifying-glass" class="size-12 mx-auto mb-4 opacity-20" />
                                <p>{{ __('No questions found in the bank for this exam.') }}</p>
                                <flux:button variant="ghost" size="sm" wire:click="$set('showModal', true)">{{ __('Add your first question') }}</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-32 bg-zinc-50 dark:bg-zinc-900/50 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
            <div class="p-4 bg-white dark:bg-zinc-800 rounded-full shadow-sm mb-6">
                <flux:icon icon="academic-cap" class="size-12 text-blue-500" />
            </div>
            <flux:heading size="lg">{{ __('Select an Examination') }}</flux:heading>
            <flux:text class="text-zinc-500 max-w-xs text-center mt-2">{{ __('Please choose a target exam from the dropdown above to manage its question bank.') }}</flux:text>
        </div>
    @endif

    {{-- Add/Edit Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-4xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Update Question') : __('Create New Question') }}</flux:heading>
                <flux:subheading>{{ __('Define the question content and the correct response.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                <div class="md:col-span-3 space-y-6">
                    <flux:textarea label="{{ __('Question Text') }}" wire:model="question_text" rows="8" placeholder="{{ __('Type the question content here...') }}" />
                </div>

                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Options') }}</flux:label>
                        <flux:button variant="ghost" size="xs" icon="plus" wire:click="addOption">{{ __('Add') }}</flux:button>
                    </div>

                    <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                        <flux:radio.group wire:model="correct_index">
                            @foreach($options as $index => $option)
                                <div class="group relative flex items-start gap-2 bg-zinc-50 dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 mb-3" wire:key="option-{{ $index }}">
                                    <div class="mt-2">
                                        <flux:radio value="{{ $index }}" />
                                    </div>
                                    <flux:textarea class="flex-1 bg-transparent border-none focus:ring-0 p-0 text-sm" wire:model="options.{{ $index }}.text" placeholder="{{ __('Option text...') }}" rows="2" />
                                    
                                    @if(count($options) > 2)
                                        <button type="button" wire:click="removeOption({{ $index }})" class="opacity-0 group-hover:opacity-100 transition-opacity absolute -right-2 -top-2 bg-red-100 text-red-600 rounded-full p-1 shadow-sm">
                                            <flux:icon icon="x-mark" variant="mini" class="size-3" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </flux:radio.group>
                    </div>
                    <flux:text size="xs" class="text-zinc-500 italic">{{ __('Click the radio button to mark as correct.') }}</flux:text>
                </div>
            </div>

            <div class="flex gap-2 justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="showModal = false">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save to Bank') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Import Modal --}}
    <flux:modal wire:model="showImportModal" class="w-full max-w-lg">
        <form wire:submit="importCsv" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Question Bank') }}</flux:heading>
                <flux:subheading>{{ __('Upload a CSV file to bulk add questions to this exam.') }}</flux:subheading>
            </div>

            <div class="p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl bg-zinc-50 dark:bg-zinc-900 flex flex-col items-center text-center">
                <flux:icon icon="arrow-up-tray" class="size-10 text-zinc-400 mb-4" />
                <input type="file" wire:model="csvFile" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                @error('csvFile') <flux:text color="danger" size="xs" class="mt-2">{{ $message }}</flux:text> @enderror
            </div>

            <div class="space-y-3">
                <flux:text size="sm" class="font-bold">{{ __('CSV Template Requirements:') }}</flux:text>
                <ul class="text-xs text-zinc-500 space-y-1 list-disc pl-4">
                    <li>{{ __('Column 1: Question Text') }}</li>
                    <li>{{ __('Column 2-5: Option 1, 2, 3, 4') }}</li>
                    <li>{{ __('Column 6: Correct Index (1, 2, 3, or 4)') }}</li>
                </ul>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button wire:click="showImportModal = false">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Begin Import') }}</span>
                    <span wire:loading>{{ __('Processing...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
