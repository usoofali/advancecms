<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Staff;
use App\ViewModels\AdmissionLetterPayload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Issue admission notification')] #[Layout('layouts.app')] class extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $letter = null;

    /** @var array<string, mixed>|null */
    public ?array $invoicePayload = null;

    /** @var int|string|null */
    public $institutionId = null;

    /** @var int|string|null */
    public $departmentId = null;

    /** @var int|string|null */
    public $programId = null;

    public ?int $academicSessionId = null;

    public ?int $invoiceId = null;

    public string $fullName = '';

    public string $academicSession = '';

    public int|string $entryLevel = 100;

    public int|string $admissionYear = 0;

    public string $awardType = 'certificate';

    public string $email = '';

    public string $phone = '';

    public function mount(): void
    {
        Gate::authorize('applications.notify');

        $user = Auth::user();
        if ($user->institution_id) {
            $this->institutionId = (string) $user->institution_id;
        }

        $session = AcademicSession::where('status', 'active')->first() ?? AcademicSession::latest()->first();
        if ($session) {
            $this->academicSessionId = $session->id;
            $this->academicSession = $session->name;
            $parts = explode('/', $session->name);
            $this->admissionYear = (int) ($parts[0] ?? now()->format('Y'));
        } else {
            $year = (int) now()->format('Y');
            $this->admissionYear = $year;
            $this->academicSession = AdmissionLetterPayload::academicSessionFromAdmissionYear($year);
        }
    }

    public function updatedInstitutionId(mixed $value): void
    {
        $this->departmentId = null;
        $this->programId = null;
        $this->invoiceId = null;
    }

    public function updatedDepartmentId(mixed $value): void
    {
        $this->programId = null;
        $this->invoiceId = null;
    }

    public function updatedProgramId(mixed $value): void
    {
        $this->invoiceId = null;
        if ($value === null || $value === '') {
            return;
        }

        $program = Program::query()->find((int) $value);
        if ($program !== null) {
            $this->awardType = $program->award_type;
        }
    }

    public function updatedAcademicSessionId(mixed $value): void
    {
        $this->invoiceId = null;
        if ($value) {
            $session = AcademicSession::find((int) $value);
            if ($session) {
                $this->academicSession = $session->name;
                $parts = explode('/', $session->name);
                if (isset($parts[0]) && is_numeric($parts[0])) {
                    $this->admissionYear = (int) $parts[0];
                }
            }
        } else {
            $this->academicSession = '';
        }
    }

    public function updatedAdmissionYear(mixed $value): void
    {
        $y = (int) $value;
        if ($y >= 1990 && $y <= 2100) {
            $session = AcademicSession::where('name', 'like', "{$y}/%")->first();
            if ($session) {
                if ($this->academicSessionId !== $session->id) {
                    $this->academicSessionId = $session->id;
                    $this->academicSession = $session->name;
                    $this->invoiceId = null;
                }
            } else {
                $this->academicSession = AdmissionLetterPayload::academicSessionFromAdmissionYear($y);
            }
        }
    }

    public function resetLetter(): void
    {
        $this->letter = null;
        $this->invoicePayload = null;
        $this->departmentId = null;
        $this->programId = null;
        $this->invoiceId = null;
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
        Gate::authorize('applications.notify');

        $user = Auth::user();
        $rules = [
            'fullName' => ['required', 'string', 'max:255'],
            'academicSessionId' => ['required', 'exists:academic_sessions,id'],
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
            'invoiceId' => [
                'required',
                Rule::exists('invoices', 'id')
                    ->where('institution_id', $institutionId)
                    ->where('academic_session_id', (int) $this->academicSessionId)
                    ->where('category', Invoice::CATEGORY_REGISTRATION),
            ],
        ], [
            'invoiceId.required' => __('A registration fee invoice is required and must be selected.'),
            'invoiceId.exists' => __('The selected registration invoice must match the institution, academic session, and category.'),
        ]);

        $institution = Institution::query()->findOrFail($institutionId);

        $program = Program::query()
            ->whereKey((int) $this->programId)
            ->where('institution_id', $institutionId)
            ->where('department_id', $departmentId)
            ->firstOrFail();

        $invoice = Invoice::query()
            ->with(['items', 'academicSession'])
            ->findOrFail((int) $this->invoiceId);

        $adminStaff = Staff::where('institution_id', $institutionId)
            ->where('role_id', 2)
            ->whereNotNull('signature_path')
            ->first();

        $this->invoicePayload = [
            'invoice_id' => $invoice->id,
            'invoice_no' => 'INV-REG-'.strtoupper(Str::random(6)),
            'title' => $invoice->title,
            'date_issued' => now()->format('d/m/Y'),
            'candidate_name' => strtoupper($this->fullName),
            'program_name' => $program->name,
            'session_name' => $this->academicSession,
            'level' => $this->entryLevel.' LEVEL',
            'items' => $invoice->items->map(fn ($item) => [
                'name' => $item->item_name,
                'amount' => (float) $item->amount,
            ])->toArray(),
            'total_amount' => (float) $invoice->total_amount,
            'bank_name' => $invoice->bank_name,
            'account_name' => $invoice->account_name,
            'account_number' => $invoice->account_number,
            'signature_url' => $adminStaff?->signature_path ? asset('storage/'.$adminStaff->signature_path) : null,
            'institution_name' => $institution->name,
            'institution_address' => $institution->address,
            'institution_email' => $institution->email,
            'institution_phone' => $institution->phone,
            'institution_logo_url' => $institution->logo_url,
            'institution_meta' => $institution->meta,
        ];

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
     *     academicSessions: \Illuminate\Database\Eloquent\Collection<int, AcademicSession>,
     *     invoices: \Illuminate\Support\Collection<int, Invoice>,
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

        $invoices = collect();
        if ($instId !== null && $this->academicSessionId) {
            $invoices = Invoice::query()
                ->where('institution_id', $instId)
                ->where('academic_session_id', (int) $this->academicSessionId)
                ->where('category', Invoice::CATEGORY_REGISTRATION)
                ->where(function ($q) {
                    $q->whereNull('department_id')
                        ->when($this->departmentId, fn ($dq) => $dq->orWhere('department_id', (int) $this->departmentId));
                })
                ->where(function ($q) {
                    $q->whereNull('program_id')
                        ->when($this->programId, fn ($pq) => $pq->orWhere('program_id', (int) $this->programId));
                })
                ->with('items')
                ->orderBy('title')
                ->get();
        }

        return [
            'institutions' => Institution::query()->orderBy('name')->get(),
            'mustPickInstitution' => Auth::user()->institution_id === null,
            'departments' => $departments,
            'programs' => $programs,
            'academicSessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'invoices' => $invoices,
            'canPickDepartment' => $instId !== null,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    @if ($letter)
        @php
            $letterPrintFilename = \Illuminate\Support\Str::slug((string) ($letter['addressee_full_name'] ?? ''));
            if ($letterPrintFilename === '') {
                $letterPrintFilename = 'admission-notification';
            }
        @endphp

        <div class="print:hidden">
            <flux:heading size="xl">{{ __('Admission Notification & Official Invoice') }}</flux:heading>
            <flux:subheading>{{ __('Review both documents below. Print individually or print both simultaneously using the actions below.') }}</flux:subheading>
        </div>

        <div class="w-full min-w-0 space-y-8 print:space-y-0">
            {{-- Notification Letter Sheet --}}
            <div id="printable-notification" class="w-full min-w-0 flex justify-center print:block overflow-x-auto">
                <x-admission-letter.sheet :letter="$letter" :print-filename="$letterPrintFilename" :show-actions="false" />
            </div>

            {{-- Page Break for Dual Print --}}
            <div class="print-page-break hidden print:block"></div>

            {{-- Linked Invoice Sheet --}}
            @if ($invoicePayload)
                <div id="printable-invoice" class="w-full min-w-0 flex justify-center print:block overflow-x-auto">
                    <div class="a4-page mx-auto bg-white text-[#1a1a1a] shadow-xl print:shadow-none p-4 sm:p-8 print:p-8 font-sans text-[10pt] border border-zinc-200 print:border-none">
                        {{-- Top Decorative Bar --}}
                        <div class="h-2 w-full mb-4" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>

                        {{-- Institution Header --}}
                        <div class="flex flex-col items-center mb-4 pb-3">
                            @if($invoicePayload['institution_logo_url'])
                                <img src="{{ $invoicePayload['institution_logo_url'] }}" alt="{{ $invoicePayload['institution_name'] }}" class="h-16 mb-2 object-contain">
                            @endif
                            <h1 class="text-xl font-bold uppercase tracking-wider text-center text-[#1a3c6b]">{{ $invoicePayload['institution_name'] }}</h1>
                            @if($invoicePayload['institution_meta'])
                                <div class="text-xs text-center text-zinc-600">{!! $invoicePayload['institution_meta'] !!}</div>
                            @endif
                            <p class="text-[9pt] text-zinc-600 uppercase text-center max-w-lg mt-0.5">{{ $invoicePayload['institution_address'] }}</p>
                            <p class="text-[9pt] text-zinc-600 uppercase font-bold mt-1">
                                @if($invoicePayload['institution_email']) EMAIL: {{ $invoicePayload['institution_email'] }} @endif
                                @if($invoicePayload['institution_phone']) | TEL: {{ $invoicePayload['institution_phone'] }} @endif
                            </p>
                            <div class="mt-3 flex flex-col sm:flex-row justify-between w-full text-xs font-bold border-t border-zinc-200 pt-2 gap-1 sm:gap-0 print:flex-row">
                                <span class="text-zinc-600 uppercase">{{ __('Invoice Ref:') }} <span class="text-black font-mono font-semibold">{{ $invoicePayload['invoice_no'] }}</span></span>
                                <span class="text-zinc-600 uppercase">{{ __('Date Issued:') }} <span class="text-black">{{ $invoicePayload['date_issued'] }}</span></span>
                            </div>
                            <h2 class="text-sm font-bold uppercase tracking-widest mt-3 px-3 py-1 bg-zinc-100 border border-zinc-300 rounded text-[#1a3c6b]">
                                {{ __('Registration Invoice') }}
                            </h2>
                        </div>

                        {{-- Candidate / Invoice Metadata --}}
                        <div class="grid grid-cols-1 gap-1 mb-4 text-xs">
                            <div class="flex border-b border-zinc-100 py-1">
                                <span class="w-40 text-zinc-500 uppercase font-bold tracking-tight">{{ __('Candidate Name:') }}</span>
                                <span class="flex-1 uppercase font-bold text-sm text-zinc-900">{{ $invoicePayload['candidate_name'] }}</span>
                            </div>
                            <div class="flex border-b border-zinc-100 py-1">
                                <span class="w-40 text-zinc-500 uppercase font-bold tracking-tight">{{ __('Program:') }}</span>
                                <span class="flex-1 uppercase font-semibold text-zinc-800">{{ $invoicePayload['program_name'] }}</span>
                            </div>
                            <div class="flex border-b border-zinc-100 py-1">
                                <span class="w-40 text-zinc-500 uppercase font-bold tracking-tight">{{ __('Academic Session:') }}</span>
                                <span class="flex-1 uppercase font-semibold text-zinc-800">{{ $invoicePayload['session_name'] }}</span>
                            </div>
                            <div class="flex border-b border-zinc-100 py-1">
                                <span class="w-40 text-zinc-500 uppercase font-bold tracking-tight">{{ __('Level / Status:') }}</span>
                                <span class="flex-1 uppercase font-semibold text-zinc-800">{{ $invoicePayload['level'] }} &nbsp;|&nbsp; {{ __('ADMISSION CANDIDATE') }}</span>
                            </div>
                        </div>

                        {{-- Invoice Items Table --}}
                        <div class="mb-4">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b-2 border-zinc-300">
                                        <th class="py-2 uppercase font-bold text-zinc-700">{{ __('Item Description') }}</th>
                                        <th class="py-2 text-right uppercase font-bold text-zinc-700">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    @forelse($invoicePayload['items'] as $item)
                                        <tr>
                                            <td class="py-1.5 font-medium text-zinc-800">{{ $item['name'] }}</td>
                                            <td class="py-1.5 text-right font-medium text-zinc-900">₦{{ number_format($item['amount'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="py-1.5 font-medium text-zinc-800">{{ $invoicePayload['title'] }}</td>
                                            <td class="py-1.5 text-right font-medium text-zinc-900">₦{{ number_format($invoicePayload['total_amount'], 2) }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-zinc-400 font-bold">
                                        <td class="py-3 text-xs uppercase tracking-wider text-zinc-900">{{ __('Total Amount Payable') }}</td>
                                        <td class="py-3 text-right text-base text-zinc-900">₦{{ number_format($invoicePayload['total_amount'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Payment Information / Bank Details --}}
                        @if($invoicePayload['bank_name'] || $invoicePayload['account_name'] || $invoicePayload['account_number'])
                            <div class="mb-4 p-3 bg-zinc-50 border border-zinc-200 rounded">
                                <h3 class="text-[9pt] font-bold uppercase tracking-widest text-zinc-600 mb-2">{{ __('Payment Information (Bank Deposit / Transfer)') }}</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-3 print:grid-cols-3 gap-2 sm:gap-4 text-xs">
                                    <div>
                                        <span class="block text-[8pt] text-zinc-400 uppercase font-bold">{{ __('Bank Name') }}</span>
                                        <span class="font-semibold text-zinc-800">{{ $invoicePayload['bank_name'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[8pt] text-zinc-400 uppercase font-bold">{{ __('Account Name') }}</span>
                                        <span class="font-semibold text-zinc-800">{{ $invoicePayload['account_name'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[8pt] text-zinc-400 uppercase font-bold">{{ __('Account Number') }}</span>
                                        <span class="font-mono font-bold text-sm text-zinc-900">{{ $invoicePayload['account_number'] ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Footer Note --}}
                        <div class="mt-4 pt-4 border-t border-zinc-200">
                            <p class="text-[8pt] text-zinc-500 italic text-center">
                                {{ __('This is an official registration invoice issued for admission provisional acceptance. Payment receipt must be presented during final clearance.') }}
                            </p>
                        </div>

                        {{-- Bottom Decorative Bar --}}
                        <div class="h-1.5 w-full mt-4" style="background: linear-gradient(to right, #1a3c6b, #2563eb, #1a3c6b);"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Bottom Print Bar --}}
        <div class="print:hidden w-full max-w-[794px] mx-auto mt-8 flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-center gap-2 sm:gap-3 px-2 pb-6">
            <flux:button variant="outline" icon="document-text" class="w-full sm:w-auto justify-center" onclick="(function(){ if (window.printNotificationOnly) { window.printNotificationOnly('{{ $letterPrintFilename }}'); } else { document.body.classList.remove('print-only-invoice'); document.body.classList.add('print-only-notification'); window.print(); } })()">{{ __('Print Notification') }}</flux:button>
            <flux:button variant="outline" icon="receipt-percent" class="w-full sm:w-auto justify-center" onclick="(function(){ if (window.printInvoiceOnly) { window.printInvoiceOnly('{{ $letterPrintFilename }}-invoice'); } else { document.body.classList.remove('print-only-notification'); document.body.classList.add('print-only-invoice'); window.print(); } })()">{{ __('Print Invoice') }}</flux:button>
            <flux:button variant="primary" icon="printer" class="w-full sm:w-auto justify-center" onclick="(function(){ if (window.printBoth) { window.printBoth('{{ $letterPrintFilename }}-letter-and-invoice'); } else { document.body.classList.remove('print-only-notification', 'print-only-invoice'); window.print(); } })()">{{ __('Print Both (Letter & Invoice)') }}</flux:button>
            <flux:button variant="ghost" wire:click="resetLetter" icon="arrow-path" class="w-full sm:w-auto justify-center">{{ __('New Letter') }}</flux:button>
        </div>
    @else
        <div>
            <flux:heading size="xl">{{ __('Issue admission notification') }}</flux:heading>
            <flux:subheading>{{ __('For walk-in or physical assessments: enter the candidate details, then generate a printable notification letter with a linked registration fee invoice. No application record is required.') }}</flux:subheading>
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
                    <flux:field>
                        <flux:label>{{ __('Academic session') }}</flux:label>
                        <flux:select wire:model.live="academicSessionId" required placeholder="{{ __('Select session') }}">
                            @foreach ($academicSessions as $sess)
                                <flux:select.option :value="$sess->id">{{ $sess->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="academicSessionId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Admission year') }}</flux:label>
                        <flux:input wire:model.live="admissionYear" type="number" required />
                        <flux:error name="admissionYear" />
                    </flux:field>
                </div>

                {{-- Registration Fee Invoice Selection (Required) --}}
                <flux:field>
                    <flux:select
                        wire:model="invoiceId"
                        label="{{ __('Registration Fee Invoice') }}"
                        placeholder="{{ __('Select Registration Invoice') }}"
                        :disabled="! $academicSessionId"
                        required
                    >
                        @foreach ($invoices as $inv)
                            <flux:select.option :value="$inv->id">
                                {{ $inv->title }} — ₦{{ number_format($inv->total_amount, 2) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($academicSessionId && $invoices->isEmpty())
                        <flux:text size="sm" class="text-amber-600 dark:text-amber-400 mt-1">
                            {{ __('No Registration Fee invoice found for this academic session.') }}
                            <a href="{{ route('cms.invoices.create') }}" class="underline font-semibold ml-1" target="_blank">{{ __('Create invoice') }}</a>
                        </flux:text>
                    @endif
                    <flux:subheading>{{ __('An invoice with category "Registration Fee" matching the selected academic session must be linked.') }}</flux:subheading>
                    <flux:error name="invoiceId" />
                </flux:field>

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
                    <flux:button type="submit" variant="primary" icon="document-text">{{ __('Generate letter & invoice') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Simultaneous Print Handlers and Media Styles --}}
    <style>
        @media print {
            body.print-only-notification #printable-invoice {
                display: none !important;
            }
            body.print-only-invoice #printable-notification {
                display: none !important;
            }
            body.print-only-notification .print-page-break,
            body.print-only-invoice .print-page-break {
                display: none !important;
            }
            .print-page-break {
                page-break-after: always !important;
                break-after: page !important;
                height: 0 !important;
                display: block !important;
            }
        }
    </style>

    <script>
        window.printNotificationOnly = function(filename) {
            document.body.classList.remove('print-only-invoice');
            document.body.classList.add('print-only-notification');
            var prevTitle = document.title;
            if (filename) {
                document.title = filename;
            }
            var cleanup = function() {
                document.body.classList.remove('print-only-notification');
                document.title = prevTitle;
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
            window.print();
            setTimeout(cleanup, 2500);
        };

        window.printInvoiceOnly = function(filename) {
            document.body.classList.remove('print-only-notification');
            document.body.classList.add('print-only-invoice');
            var prevTitle = document.title;
            if (filename) {
                document.title = filename;
            }
            var cleanup = function() {
                document.body.classList.remove('print-only-invoice');
                document.title = prevTitle;
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
            window.print();
            setTimeout(cleanup, 2500);
        };

        window.printBoth = function(filename) {
            document.body.classList.remove('print-only-notification', 'print-only-invoice');
            var prevTitle = document.title;
            if (filename) {
                document.title = filename;
            }
            var cleanup = function() {
                document.title = prevTitle;
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
            window.print();
            setTimeout(cleanup, 2500);
        };
    </script>
</div>
