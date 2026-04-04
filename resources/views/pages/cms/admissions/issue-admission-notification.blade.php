<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\ViewModels\AdmissionLetterPayload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Issue admission notification')] #[Layout('layouts.app')] class extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $letter = null;

    /** @var int|string|null */
    public $institutionId = null;

    /** @var int|string|null */
    public $departmentId = null;

    /** @var int|string|null */
    public $programId = null;

    public string $fullName = '';

    public string $academicSession = '';

    public int|string $entryLevel = 100;

    public int|string $admissionYear = 0;

    public string $awardType = 'certificate';

    public string $email = '';

    public string $phone = '';

    public function mount(): void
    {
        $user = Auth::user();
        $year = (int) now()->format('Y');
        $this->admissionYear = $year;
        $this->academicSession = AdmissionLetterPayload::academicSessionFromAdmissionYear($year);

        if ($user->institution_id) {
            $this->institutionId = (string) $user->institution_id;
        }
    }

    public function updatedInstitutionId(mixed $value): void
    {
        $this->departmentId = null;
        $this->programId = null;
    }

    public function updatedDepartmentId(mixed $value): void
    {
        $this->programId = null;
    }

    public function updatedProgramId(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $program = Program::query()->find((int) $value);
        if ($program !== null) {
            $this->awardType = $program->award_type;
        }
    }

    public function updatedAdmissionYear(mixed $value): void
    {
        $y = (int) $value;
        if ($y >= 1990 && $y <= 2100) {
            $this->academicSession = AdmissionLetterPayload::academicSessionFromAdmissionYear($y);
        }
    }

    public function resetLetter(): void
    {
        $this->letter = null;
        $this->departmentId = null;
        $this->programId = null;
    }

    public function effectiveInstitutionId(): ?int
    {
        $user = Auth::user();
        if ($user->institution_id) {
            return (int) $user->institution_id;
        }

        if ($this->institutionId !== null && $this->institutionId !== '') {
            return (int) $this->institutionId;
        }

        return null;
    }

    public function generateLetter(): void
    {
        $user = Auth::user();
        $rules = [
            'fullName' => ['required', 'string', 'max:255'],
            'academicSession' => ['required', 'string', 'max:120'],
            'entryLevel' => ['required', 'integer', 'min:100', 'max:900'],
            'admissionYear' => ['required', 'integer', 'min:1990', 'max:2100'],
            'awardType' => ['required', Rule::in(['certificate', 'diploma', 'degree'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        if (! $user->institution_id) {
            $rules['institutionId'] = ['required', 'exists:institutions,id'];
        }

        $this->validate($rules);

        $institutionId = $user->institution_id
            ? (int) $user->institution_id
            : (int) $this->institutionId;

        $departmentId = (int) $this->departmentId;

        $this->validate([
            'departmentId' => [
                'required',
                Rule::exists('departments', 'id')->where('institution_id', $institutionId),
            ],
            'programId' => [
                'required',
                Rule::exists('programs', 'id')
                    ->where('institution_id', $institutionId)
                    ->where('department_id', $departmentId),
            ],
        ]);

        $institution = Institution::query()->findOrFail($institutionId);

        $program = Program::query()
            ->whereKey((int) $this->programId)
            ->where('institution_id', $institutionId)
            ->where('department_id', $departmentId)
            ->firstOrFail();

        $this->letter = AdmissionLetterPayload::forImpromptuNotification($institution, [
            'addressee_full_name' => $this->fullName,
            'academic_session_label' => $this->academicSession,
            'program_name' => $program->name,
            'entry_level' => (int) $this->entryLevel,
            'award_type' => $this->awardType,
            'admission_year' => (int) $this->admissionYear,
            'email' => $this->email !== '' ? $this->email : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
        ]);
    }

    /**
     * @return array{
     *     institutions: \Illuminate\Database\Eloquent\Collection<int, Institution>,
     *     mustPickInstitution: bool,
     *     departments: \Illuminate\Database\Eloquent\Collection<int, Department>,
     *     programs: \Illuminate\Database\Eloquent\Collection<int, Program>,
     *     canPickDepartment: bool,
     * }
     */
    public function with(): array
    {
        $instId = $this->effectiveInstitutionId();
        $departments = $instId !== null
            ? Department::query()->where('institution_id', $instId)->orderBy('name')->get()
            : collect();

        $programs = ($instId !== null && $this->departmentId !== null && $this->departmentId !== '')
            ? Program::query()
                ->where('institution_id', $instId)
                ->where('department_id', (int) $this->departmentId)
                ->orderBy('name')
                ->get()
            : collect();

        return [
            'institutions' => Institution::query()->orderBy('name')->get(),
            'mustPickInstitution' => Auth::user()->institution_id === null,
            'departments' => $departments,
            'programs' => $programs,
            'canPickDepartment' => $instId !== null,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    @if ($letter)
        <div class="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Admission notification letter') }}</flux:heading>
                <flux:subheading>{{ __('Review the letter below, then print or save as PDF.') }}</flux:subheading>
            </div>
            <flux:button variant="primary" wire:click="resetLetter" icon="arrow-path">{{ __('New letter') }}</flux:button>
        </div>

        @php
            $letterPrintFilename = \Illuminate\Support\Str::slug((string) ($letter['addressee_full_name'] ?? ''));
            if ($letterPrintFilename === '') {
                $letterPrintFilename = null;
            }
        @endphp
        <div class="w-full min-w-0 flex justify-center print:block">
            <x-admission-letter.sheet :letter="$letter" :print-filename="$letterPrintFilename" />
        </div>
    @else
        <div>
            <flux:heading size="xl">{{ __('Issue admission notification') }}</flux:heading>
            <flux:subheading>{{ __('For walk-in or physical assessments: enter the candidate details, then generate a printable notification letter. No application record is required.') }}</flux:subheading>
        </div>

        <flux:card class="max-w-2xl">
            <form wire:submit="generateLetter" class="space-y-6">
                @if ($mustPickInstitution)
                    <flux:select wire:model.live="institutionId" label="{{ __('Institution') }}" placeholder="{{ __('Select institution') }}" required>
                      
                        @foreach ($institutions as $inst)
                            <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select
                    wire:model.live="departmentId"
                    label="{{ __('Department') }}"
                    placeholder="{{ __('Select department') }}"
                    :disabled="! $canPickDepartment"
                    required
                >
                    @foreach ($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model.live="programId"
                    label="{{ __('Program') }}"
                    placeholder="{{ __('Select program') }}"
                    :disabled="! $departmentId"
                    required
                >
                    @foreach ($programs as $prog)
                        <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="fullName" label="{{ __('Full name') }}" required />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model.live="admissionYear" type="number" label="{{ __('Admission year') }}" required />
                    <flux:input wire:model="academicSession" label="{{ __('Academic session') }}" required />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="entryLevel" type="number" label="{{ __('Entry level') }}" required />
                    <flux:select wire:model="awardType" label="{{ __('Award type') }}">
                        <flux:select.option value="certificate">{{ __('Certificate') }}</flux:select.option>
                        <flux:select.option value="diploma">{{ __('Diploma') }}</flux:select.option>
                        <flux:select.option value="degree">{{ __('Degree') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="email" type="email" label="{{ __('Email') }} ({{ __('optional') }})" />
                    <flux:input wire:model="phone" label="{{ __('Phone') }} ({{ __('optional') }})" />
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="document-text">{{ __('Generate letter') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</div>
