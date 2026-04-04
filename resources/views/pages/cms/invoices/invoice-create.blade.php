<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Manage Invoice')] class extends Component
{
    public ?Invoice $invoice = null;

    /** @var int|string|null */
    public $institution_id = null;

    public string $title = '';
    public $academic_session_id;
    public string $category = Invoice::CATEGORY_GENERAL;
    public $semester_id;
    public string $due_date = '';
    public $department_id;
    public $program_id;
    public string $level = '';
    
    public bool $is_required_for_results = false;
    public bool $is_required_for_exams = false;

    public string $account_name = '';
    public string $account_number = '';
    public string $bank_name = '';

    public array $items = [['item_name' => '', 'amount' => '']];

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice && $invoice->exists) {
            $this->invoice = $invoice;
            $this->institution_id = (int) $invoice->institution_id;
            $this->title = $invoice->title;
            $this->academic_session_id = $invoice->academic_session_id;
            $this->due_date = $invoice->due_date->format('Y-m-d');
            $this->department_id = $invoice->department_id;
            $this->program_id = $invoice->program_id;
            $this->level = $invoice->level ?? '';
            $this->is_required_for_results = (bool) $invoice->is_required_for_results;
            $this->is_required_for_exams = (bool) $invoice->is_required_for_exams;
            $this->category = $invoice->category ?? Invoice::CATEGORY_GENERAL;
            $this->semester_id = $invoice->semester_id;
            $this->account_name = $invoice->account_name ?? '';
            $this->account_number = $invoice->account_number ?? '';
            $this->bank_name = $invoice->bank_name ?? '';
            $this->items = $invoice->items->map(fn ($item) => [
                'item_name' => $item->item_name,
                'amount' => $item->amount,
            ])->toArray();
        } else {
            $this->academic_session_id = AcademicSession::first()?->id;
            $this->due_date = now()->addMonth()->format('Y-m-d');
        }
    }

    public function effectiveInstitutionId(): ?int
    {
        if ($this->invoice?->exists) {
            return (int) $this->invoice->institution_id;
        }

        $user = Auth::user();

        if ($user->hasRole('Super Admin')) {
            return $this->institution_id !== null && $this->institution_id !== ''
                ? (int) $this->institution_id
                : null;
        }

        return $user->institution_id ? (int) $user->institution_id : null;
    }

    public function updatedInstitutionId(): void
    {
        if (! Auth::user()->hasRole('Super Admin') || $this->invoice?->exists) {
            return;
        }

        $this->department_id = null;
        $this->program_id = null;
        $this->semester_id = null;
    }

    public function addItem()
    {
        $this->items[] = ['item_name' => '', 'amount' => ''];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save($status = 'draft')
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $isEditing = (bool) $this->invoice?->exists;

        if ($isSuperAdmin && ! $isEditing) {
            $this->validate([
                'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            ]);
        }

        $effectiveInstitutionId = $isEditing
            ? (int) $this->invoice->institution_id
            : ($isSuperAdmin
                ? (int) $this->institution_id
                : ($user->institution_id !== null ? (int) $user->institution_id : null));

        $this->validate([
            'title' => 'required|string|max:255',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'due_date' => 'required|date',
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($q) => $q->where('institution_id', $effectiveInstitutionId)
                ),
            ],
            'program_id' => [
                'nullable',
                Rule::exists('programs', 'id')->where(
                    fn ($q) => $q->where('department_id', $this->department_id)
                ),
            ],
            'category' => 'required|string',
            'is_required_for_results' => 'boolean',
            'is_required_for_exams' => 'boolean',
            'semester_id' => 'required_if:category,'.Invoice::CATEGORY_EXAM.','.Invoice::CATEGORY_RESULT.'|nullable|exists:semesters,id',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0',
        ], [
            'semester_id.required_if' => 'A semester must be selected for Examination and Result Checking fees.',
        ]);

        // Determine target_type for database compatibility
        $targetType = 'dept';
        if ($this->program_id) {
            $targetType = 'program';
        } elseif ($this->level) {
            $targetType = 'level';
        }

        if ($status === 'published' && $this->category !== Invoice::CATEGORY_GENERAL) {
            $conflictQuery = Invoice::where('category', $this->category)
                ->where('institution_id', $effectiveInstitutionId)
                ->where('academic_session_id', $this->academic_session_id)
                ->where('semester_id', $this->semester_id)
                ->where('target_type', $targetType)
                ->where('status', 'published')
                ->when($this->invoice, fn ($q) => $q->where('id', '!=', $this->invoice->id))
                ->where(function ($q) {
                    $q->where('department_id', $this->department_id)
                        ->where('program_id', $this->program_id)
                        ->where('level', $this->level);
                });

            if ($conflictQuery->exists()) {
                $this->addError('category', 'A published invoice for this category and target scope already exists.');
                return;
            }
        }

        $data = [
            'institution_id' => $effectiveInstitutionId,
            'title' => $this->title,
            'academic_session_id' => $this->academic_session_id,
            'due_date' => $this->due_date,
            'target_type' => $targetType,
            'department_id' => $this->department_id,
            'program_id' => $this->program_id ?: null,
            'level' => $this->level ?: null,
            'is_required_for_results' => $this->is_required_for_results,
            'is_required_for_exams' => $this->is_required_for_exams,
            'category' => $this->category,
            'semester_id' => $this->semester_id ?: null,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'status' => $status,
        ];

        if ($this->invoice) {
            $this->invoice->update($data);
            $invoice = $this->invoice;
            $invoice->items()->delete();
        } else {
            $data['created_by'] = Auth::id();
            $invoice = Invoice::create($data);
        }

        foreach ($this->items as $item) {
            $invoice->items()->create($item);
        }

        session()->flash('success', 'Invoice '.($this->invoice ? 'updated' : 'created').' successfully.');

        return $this->redirectRoute('cms.invoices.index', navigate: true);
    }
};
?>

<div class="max-w-4xl space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $invoice ? 'Edit Invoice' : 'Create New Invoice' }}</flux:heading>
            <flux:subheading>{{ $invoice ? 'Update invoice details and fee items.' : 'Define fee items and set target
                groups.' }}</flux:subheading>
        </div>
    </div>

    <form class="space-y-8">
        <flux:card class="space-y-6">
            @if(auth()->user()->hasRole('Super Admin'))
                @if($invoice)
                    <flux:field>
                        <flux:label>{{ __('Institution') }}</flux:label>
                        <flux:text class="text-zinc-900 dark:text-zinc-100">{{ $invoice->institution?->name ?? '—' }}</flux:text>
                    </flux:field>
                @else
                    <flux:field>
                        <flux:label>{{ __('Institution') }}</flux:label>
                        <flux:select wire:model.live="institution_id" :placeholder="__('Select Institution')" required>
                            <flux:select.option value="">{{ __('Select Institution') }}</flux:select.option>
                            @foreach(Institution::query()->where('status', 'active')->orderBy('name')->get() as $institution)
                                <flux:select.option :value="$institution->id">{{ $institution->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="institution_id" />
                    </flux:field>
                @endif
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Info -->
                <flux:field>
                    <flux:label>Invoice Title</flux:label>
                    <flux:input wire:model="title" placeholder="e.g. ND1 Registration Fees" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Academic Session</flux:label>
                    <flux:select wire:model="academic_session_id" required>
                        @foreach(\App\Models\AcademicSession::all() as $session)
                        <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="academic_session_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Due Date</flux:label>
                    <flux:input type="date" wire:model="due_date" />
                    <flux:error name="due_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Invoice Category</flux:label>
                    <flux:select wire:model="category">
                        <flux:select.option value="{{ Invoice::CATEGORY_GENERAL }}">General Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_ADMISSION }}">Admission Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_EXAM }}">Examination Fee (Locks Exam Card)</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_RESULT }}">Result Checking Fee (Locks Results)</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_REGISTRATION }}">Registration Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_INDEXING }}">Indexing Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_PRACTICAL }}">Practical Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_PROJECT }}">Project Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_REFRESHMENT }}">Refreshment Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_NATIONAL }}">National Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_INDUCTION }}">Induction Fee</flux:select.option>
                        <flux:select.option value="{{ Invoice::CATEGORY_CERTIFICATE }}">Certificate Fee</flux:select.option>
                    </flux:select>
                    <flux:error name="category" />
                </flux:field>

                <flux:field>
                    <flux:label>Semester (Optional)</flux:label>
                    <flux:select wire:model="semester_id" :disabled="!$academic_session_id" :placeholder="__('Select Semester')">
                        <flux:select.option value="">{{ __('All Semesters') }}</flux:select.option>
                        @if ($academic_session_id)
                            @foreach(\App\Models\Semester::where('academic_session_id', $academic_session_id)->get() as $semester)
                            <flux:select.option :value="$semester->id">{{ $semester->name }}</flux:select.option>
                            @endforeach
                        @endif
                    </flux:select>
                    <flux:error name="semester_id" />
                </flux:field>
            </div>

            <!-- Restriction Flags -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:switch wire:model="is_required_for_results" label="{{ __('Required for Result Checking') }}" description="{{ __('Students must pay this invoice before viewing their results.') }}" />
                <flux:switch wire:model="is_required_for_exams" label="{{ __('Required for Exam Card') }}" description="{{ __('Students must pay this invoice before downloading their exam card.') }}" />
            </div>

            <!-- Bank Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:field>
                    <flux:label>Bank Name</flux:label>
                    <flux:input wire:model="bank_name" placeholder="e.g. Zenith Bank" />
                    <flux:error name="bank_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Account Name</flux:label>
                    <flux:input wire:model="account_name" placeholder="e.g. CSHT Student Fees" />
                    <flux:error name="account_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Account Number</flux:label>
                    <flux:input wire:model="account_number" placeholder="e.g. 1234567890" />
                    <flux:error name="account_number" />
                </flux:field>
            </div>

            <!-- Targeting Hierarchy -->
            @php
                $effectiveInstitutionId = $this->effectiveInstitutionId();
                $departmentSelectDisabled = auth()->user()->hasRole('Super Admin') && ! $invoice && ! $effectiveInstitutionId;
                $departmentsForInstitution = $effectiveInstitutionId
                    ? Department::query()->where('institution_id', $effectiveInstitutionId)->orderBy('name')->get()
                    : collect();
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model.live="department_id" :placeholder="__('Select Department')" :disabled="$departmentSelectDisabled" required>
                        @foreach($departmentsForInstitution as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="department_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Program (Optional)</flux:label>
                    <flux:select wire:model="program_id" :placeholder="__('All Programs')" :disabled="!$department_id">
                        @if ($department_id)
                        @foreach(\App\Models\Program::where('department_id', $department_id)->get() as $prog)
                        <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                        @endforeach
                        @endif
                    </flux:select>
                    <flux:error name="program_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Level (Optional)</flux:label>
                    <flux:input wire:model="level" placeholder="e.g. 100 or leave for All" />
                    <flux:error name="level" />
                </flux:field>
            </div>
        </flux:card>

        <!-- Invoice Items -->
        <flux:card class="space-y-6">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Invoice Items</flux:heading>
                <flux:button variant="subtle" icon="plus" size="sm" wire:click="addItem">Add Item</flux:button>
            </div>

            <div class="space-y-4">
                @foreach ($items as $index => $item)
                <div class="flex items-start gap-4" wire:key="item-{{ $index }}">
                    <div class="flex-1">
                        <flux:input wire:model="items.{{ $index }}.item_name"
                            placeholder="Item Name (e.g. Tuition Fee)" />
                        <flux:error name="items.{{ $index }}.item_name" />
                    </div>
                    <div class="w-48">
                        <flux:input type="number" wire:model="items.{{ $index }}.amount" placeholder="Amount"
                            prefix="₦" />
                        <flux:error name="items.{{ $index }}.amount" />
                    </div>
                    <div class="pt-2">
                        <flux:button variant="ghost" icon="trash" size="sm" variant="danger"
                            wire:click="removeItem({{ $index }})" :disabled="count($items) === 1" />
                    </div>
                </div>
                @endforeach
            </div>
        </flux:card>

        <div class="flex items-center justify-end gap-4">
            <flux:button variant="ghost" href="{{ route('cms.invoices.index') }}">Cancel</flux:button>
            <flux:button variant="subtle" wire:click="save('draft')">{{ __('Save as Draft') }}</flux:button>
            <flux:button variant="primary" wire:click="save('published')">{{ __('Publish Invoice') }}</flux:button>
        </div>
    </form>
</div>