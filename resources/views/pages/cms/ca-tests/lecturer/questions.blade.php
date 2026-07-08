<?php

use App\Models\CaTest;
use App\Models\CaQuestion;
use App\Models\CaQuestionOption;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] #[Title('Manage CA Questions')] class extends Component {
    use WithPagination, WithFileUploads;

    #[Url]
    public $test_id = null;

    public $question_id = null;
    public string $text = '';
    public int $coin_reward = 2;
    public float $marks = 1.0;

    // Options
    public array $options = [
        ['text' => '', 'is_correct' => true],
        ['text' => '', 'is_correct' => false],
        ['text' => '', 'is_correct' => false],
        ['text' => '', 'is_correct' => false],
    ];

    public bool $showModal = false;
    public bool $showImportModal = false;
    public $csvFile;

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
            ->when($isRestrictedLecturer, function ($query) use ($user) {
                $query->whereIn('course_id', function ($sub) use ($user) {
                    $sub->select('course_id')
                        ->from('course_allocations')
                        ->where('user_id', $user->id);
                });
            })
            ->when(!empty($scopedDeptIds) && !$isSuperAdmin && !$isInstAdmin, function ($query) use ($scopedDeptIds) {
                $query->whereHas('course', function ($cq) use ($scopedDeptIds) {
                    $cq->whereIn('department_id', $scopedDeptIds);
                });
            })
            ->get();

        $selectedTest = null;
        $questions = [];

        if ($this->test_id) {
            $selectedTest = CaTest::find($this->test_id);
            if ($selectedTest) {
                $questions = CaQuestion::where('ca_test_id', $this->test_id)->with('options')->paginate(50);
            }
        }

        return [
            'tests' => $tests,
            'selectedTest' => $selectedTest,
            'questions' => $questions,
        ];
    }

    public function addOption(): void
    {
        $this->options[] = ['text' => '', 'is_correct' => false];
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) > 2) {
            unset($this->options[$index]);
            $this->options = array_values($this->options);
        }
    }

    public function setCorrectOption(int $index): void
    {
        foreach ($this->options as $k => $opt) {
            $this->options[$k]['is_correct'] = ($k === $index);
        }
    }

    public function editQuestion(int $id): void
    {
        $question = CaQuestion::with('options')->findOrFail($id);
        $this->question_id = $question->id;
        $this->text = $question->text;
        $this->coin_reward = $question->coin_reward;
        $this->marks = $question->marks;

        $this->options = [];
        foreach ($question->options as $opt) {
            $this->options[] = [
                'id' => $opt->id,
                'text' => $opt->text,
                'is_correct' => $opt->is_correct,
            ];
        }

        $this->showModal = true;
    }

    public function saveQuestion(): void
    {
        $this->validate([
            'test_id' => 'required|exists:ca_tests,id',
            'text' => 'required|string',
            'marks' => 'required|numeric|min:0.1',
            'coin_reward' => 'required|integer|min:0',
            'options' => 'required|array|min:2',
            'options.*.text' => 'required|string',
        ]);

        $hasCorrect = false;
        foreach ($this->options as $opt) {
            if ($opt['is_correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            $this->addError('options', 'Please mark at least one option as correct.');
            return;
        }

        if ($this->question_id) {
            $question = CaQuestion::find($this->question_id);
            $question->update([
                'text' => $this->text,
                'marks' => $this->marks,
                'coin_reward' => $this->coin_reward,
            ]);

            $question->options()->delete();
            foreach ($this->options as $opt) {
                CaQuestionOption::create([
                    'ca_question_id' => $question->id,
                    'text' => $opt['text'],
                    'is_correct' => $opt['is_correct'],
                ]);
            }
        } else {
            $question = CaQuestion::create([
                'ca_test_id' => $this->test_id,
                'text' => $this->text,
                'marks' => $this->marks,
                'coin_reward' => $this->coin_reward,
            ]);

            foreach ($this->options as $opt) {
                CaQuestionOption::create([
                    'ca_question_id' => $question->id,
                    'text' => $opt['text'],
                    'is_correct' => $opt['is_correct'],
                ]);
            }
        }

        $this->showModal = false;
        $this->resetQuestionForm();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Question saved successfully.',
        ]);
    }

    public function deleteQuestion(int $id): void
    {
        CaQuestion::findOrFail($id)->delete();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Question deleted.',
        ]);
    }

    public function resetQuestionForm(): void
    {
        $this->question_id = null;
        $this->text = '';
        $this->marks = 1.0;
        $this->coin_reward = 2;
        $this->options = [
            ['text' => '', 'is_correct' => true],
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
            ['text' => '', 'is_correct' => false],
        ];
    }

    public function importCsv(): void
    {
        Gate::authorize('ca_tests.edit'); // Use edit or create based on policy

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $this->validate([
            'test_id' => [
                'required',
                'exists:ca_tests,id',
                function ($attribute, $value, $fail) use ($isRestrictedLecturer, $user, $scopedDeptIds) {
                    $test = CaTest::find($value);
                    if ($test) {
                        if ($isRestrictedLecturer) {
                            $isAllocated = DB::table('course_allocations')
                                ->where('user_id', $user->id)
                                ->where('course_id', $test->course_id)
                                ->exists();
                            if (!$isAllocated) {
                                $fail('You do not have access to this CA test.');
                            }
                        } elseif (!empty($scopedDeptIds)) {
                            $course = \App\Models\Course::find($test->course_id);
                            if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                                $fail('This CA test belongs to a course outside your department.');
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
                // Expected: Question Text, Opt1, Opt2, Opt3, Opt4, Correct Index (1-4), Marks, Coins
                if (count($row) < 6)
                    continue;

                // Ensure all columns in the row are valid UTF-8
                $row = array_map(function ($value) {
                    if ($value === null) {
                        return null;
                    }
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        // Attempt to convert from Windows-1252 to UTF-8
                        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                    }
                    // Strip/replace any remaining invalid UTF-8 sequences just in case
                    return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }, $row);

                $qText = $row[0];
                $opts = [$row[1], $row[2], $row[3], $row[4]];
                $correctIdx = (int) $row[5] - 1; // Convert 1-4 to 0-3
                $marks = isset($row[6]) && is_numeric($row[6]) ? (float) $row[6] : 1.0;
                $coins = isset($row[7]) && is_numeric($row[7]) ? (int) $row[7] : 2;

                $question = CaQuestion::create([
                    'ca_test_id' => $this->test_id,
                    'text' => $qText,
                    'marks' => $marks,
                    'coin_reward' => $coins,
                ]);

                foreach ($opts as $idx => $optText) {
                    if (empty($optText))
                        continue;
                    $question->options()->create([
                        'text' => $optText,
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
        
        $this->redirectRoute('cms.ca-tests.lecturer.questions', ['test_id' => $this->test_id], navigate: true);
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('ca_tests.view');

        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isInstAdmin = $user->hasRole('Institutional Admin');
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));
        $isRestrictedLecturer = !$isSuperAdmin && !$isInstAdmin && empty($scopedDeptIds);

        $this->validate([
            'test_id' => [
                'required',
                'exists:ca_tests,id',
                function ($attribute, $value, $fail) use ($isRestrictedLecturer, $user, $scopedDeptIds) {
                    $test = CaTest::find($value);
                    if ($test) {
                        if ($isRestrictedLecturer) {
                            $isAllocated = DB::table('course_allocations')
                                ->where('user_id', $user->id)
                                ->where('course_id', $test->course_id)
                                ->exists();
                            if (!$isAllocated) {
                                $fail('You do not have access to this CA test.');
                            }
                        } elseif (!empty($scopedDeptIds)) {
                            $course = \App\Models\Course::find($test->course_id);
                            if ($course && !in_array($course->department_id, $scopedDeptIds)) {
                                $fail('This CA test belongs to a course outside your department.');
                            }
                        }
                    }
                }
            ],
        ]);

        $test = CaTest::findOrFail($this->test_id);
        $questions = CaQuestion::where('ca_test_id', $this->test_id)->with('options')->get();

        $filename = "ca_questions_" . Str::slug($test->title) . "_" . now()->format('YmdHis') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($questions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Question Text', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'Correct Index', 'Marks', 'Coins']);

            foreach ($questions as $q) {
                $options = $q->options->values();
                $correctIdx = 1;
                foreach ($options as $idx => $opt) {
                    if ($opt->is_correct) {
                        $correctIdx = $idx + 1;
                        break;
                    }
                }

                fputcsv($file, [
                    $q->text,
                    $options->get(0)?->text ?? '',
                    $options->get(1)?->text ?? '',
                    $options->get(2)?->text ?? '',
                    $options->get(3)?->text ?? '',
                    $correctIdx,
                    $q->marks,
                    $q->coin_reward,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <flux:heading size="xl">{{ __('Question Bank') }}</flux:heading>
            <flux:subheading>{{ __('Manage questions for your CA tests.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" :href="route('cms.ca-tests.lecturer.index')" wire:navigate icon="arrow-left" class="self-start sm:self-auto">
            {{ __('Back to CA Tests') }}
        </flux:button>
    </div>

    @if($selectedTest)
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <flux:heading size="lg">{{ __('Questions for :title', ['title' => $selectedTest->title]) }}</flux:heading>
            <div class="flex flex-wrap gap-2 items-center">
                @can('ca_tests.view')
                    <flux:button icon="arrow-down-tray" variant="subtle" wire:click="exportCsv">
                        {{ __('Export CSV') }}
                    </flux:button>
                @endcan
                @can('ca_tests.edit')
                    <flux:button icon="arrow-up-tray" variant="subtle" wire:click="$set('showImportModal', true)">
                        {{ __('Import CSV') }}
                    </flux:button>
                @endcan
                <flux:button variant="primary" icon="plus" wire:click="resetQuestionForm(); $set('showModal', true)">
                    {{ __('Add Question') }}
                </flux:button>
            </div>
        </div>

        <flux:table :paginate="$questions">
            <flux:table.columns>
                <flux:table.column>{{ __('Question') }}</flux:table.column>
                <flux:table.column>{{ __('Marks') }}</flux:table.column>
                <flux:table.column>{{ __('Coins') }}</flux:table.column>
                <flux:table.column>{{ __('Options & Answers') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($questions as $index => $q)
                    <flux:table.row>
                        <flux:table.cell>
                            <span class="font-bold mr-2">{{ $index + 1 }}.</span>
                            {{ Str::limit($q->text, 50) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $q->marks }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1 text-amber-600 dark:text-amber-500">
                                <flux:icon icon="currency-dollar" class="size-4" />
                                {{ $q->coin_reward }}
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
                                            {{ $option->text }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    wire:click="editQuestion({{ $q->id }})" />
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="deleteQuestion({{ $q->id }})"
                                    wire:confirm="Are you sure?" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                            {{ __('No questions added to this test yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showModal" class="w-full max-w-3xl">
        <form wire:submit="saveQuestion" class="space-y-6">
            <flux:heading size="lg">{{ $question_id ? __('Edit Question') : __('New Question') }}</flux:heading>

            <div class="space-y-4">
                <flux:textarea wire:model="text" label="{{ __('Question Text') }}" required rows="3" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="number" step="0.1" wire:model="marks" label="{{ __('Marks (Weight)') }}"
                        required />
                    <flux:input type="number" wire:model="coin_reward" label="{{ __('Coin Reward') }}" required />
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center mb-4">
                    <flux:heading size="md">{{ __('Options') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="addOption">{{ __('Add Option') }}</flux:button>
                </div>

                @error('options')
                    <div class="text-red-500 text-sm mb-4">{{ $message }}</div>
                @enderror

                <div class="space-y-3">
                    @foreach($options as $index => $option)
                        <div class="flex items-center gap-3 w-full">
                            <input type="radio" name="correct_option" class="size-5 text-emerald-600 focus:ring-emerald-500"
                                wire:click="setCorrectOption({{ $index }})" {{ $option['is_correct'] ? 'checked' : '' }} />

                            <div class="flex-1">
                                <flux:input wire:model="options.{{ $index }}.text" placeholder="{{ __('Option Text') }}"
                                    required />
                            </div>

                            @if(count($options) > 2)
                                <flux:button size="sm" variant="ghost" class="text-red-500" icon="trash"
                                    wire:click="removeOption({{ $index }})" />
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-zinc-500 mt-2">{{ __('Select the radio button next to the correct answer.') }}
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="$set('showModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Question') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Import Modal --}}
    <flux:modal wire:model="showImportModal" class="w-full max-w-lg">
        <form wire:submit="importCsv" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Question Bank') }}</flux:heading>
                <flux:subheading>{{ __('Upload a CSV file to bulk add questions to this CA test.') }}</flux:subheading>
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
                    <li>{{ __('Column 7: Marks (optional, default 1.0)') }}</li>
                    <li>{{ __('Column 8: Coins (optional, default 2)') }}</li>
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