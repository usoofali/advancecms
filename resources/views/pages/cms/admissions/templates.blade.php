<?php

use App\Models\AdmissionLetterTemplate;
use App\Models\Institution;
use App\ViewModels\AdmissionLetterPayload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Letter Templates')] #[Layout('layouts.app')] class extends Component
{
    public ?int $institutionId = null;

    public string $activeType = AdmissionLetterTemplate::TYPE_NOTIFICATION;

    public string $letterTitle = '';

    public string $salutation = '';

    public string $openingText = '';

    public string $bodyText = '';

    public string $conditionsText = '';

    public string $closingText = '';

    public string $signatoryTitle = '';

    public string $signatorySubtitle = '';

    public bool $showQrCode = true;

    public string $logoPosition = 'left';

    public string $qrPosition = 'header_right';

    public bool $isCustomized = false;

    public function mount(): void
    {
        Gate::authorize('applications.notify');

        $user = Auth::user();
        if ($user->institution_id) {
            $this->institutionId = (int) $user->institution_id;
        } else {
            $firstInst = Institution::query()->orderBy('name')->first();
            $this->institutionId = $firstInst?->id;
        }

        $this->loadTemplate();
    }

    public function updatedInstitutionId(): void
    {
        $this->loadTemplate();
    }

    public function setTab(string $type): void
    {
        $this->activeType = $type;
        $this->loadTemplate();
    }

    public function loadTemplate(): void
    {
        if (! $this->institutionId) {
            return;
        }

        $institution = Institution::find($this->institutionId);
        if (! $institution) {
            return;
        }

        $record = AdmissionLetterTemplate::where('institution_id', $this->institutionId)
            ->where('type', $this->activeType)
            ->first();

        $this->isCustomized = $record !== null;

        $template = $record ?? AdmissionLetterTemplate::forInstitution($institution, $this->activeType);

        $this->letterTitle = $template->letter_title ?? '';
        $this->salutation = $template->salutation ?? '';
        $this->openingText = $template->opening_text ?? '';
        $this->bodyText = $template->body_text ?? '';
        $this->conditionsText = $template->conditions_text ?? '';
        $this->closingText = $template->closing_text ?? '';
        $this->signatoryTitle = $template->signatory_title ?? '';
        $this->signatorySubtitle = $template->signatory_subtitle ?? '';
        $this->showQrCode = (bool) $template->show_qr_code;
        $this->logoPosition = $template->logo_position ?? 'left';
        $this->qrPosition = $template->qr_position ?? 'header_right';
    }

    public function saveTemplate(): void
    {
        Gate::authorize('applications.notify');

        $this->validate([
            'institutionId' => ['required', 'exists:institutions,id'],
            'letterTitle' => ['required', 'string', 'max:255'],
            'salutation' => ['nullable', 'string', 'max:255'],
            'openingText' => ['nullable', 'string'],
            'bodyText' => ['nullable', 'string'],
            'conditionsText' => ['nullable', 'string'],
            'closingText' => ['nullable', 'string'],
            'signatoryTitle' => ['nullable', 'string', 'max:255'],
            'signatorySubtitle' => ['nullable', 'string', 'max:255'],
            'showQrCode' => ['boolean'],
            'logoPosition' => ['required', Rule::in(['left', 'center', 'right'])],
            'qrPosition' => ['required', Rule::in(['header_right', 'footer_left', 'footer_center', 'footer_right'])],
        ]);

        AdmissionLetterTemplate::updateOrCreate(
            [
                'institution_id' => $this->institutionId,
                'type' => $this->activeType,
            ],
            [
                'letter_title' => $this->letterTitle,
                'salutation' => $this->salutation,
                'opening_text' => $this->openingText,
                'body_text' => $this->bodyText,
                'conditions_text' => $this->conditionsText,
                'closing_text' => $this->closingText,
                'signatory_title' => $this->signatoryTitle,
                'signatory_subtitle' => $this->signatorySubtitle,
                'show_qr_code' => $this->showQrCode,
                'logo_position' => $this->logoPosition,
                'qr_position' => $this->qrPosition,
            ]
        );

        $this->isCustomized = true;

        Flux::toast(
            text: __('Admission letter template saved successfully.'),
            variant: 'success'
        );
    }

    public function confirmReset(): void
    {
        $this->resetToDefault();
    }

    public function resetToDefault(): void
    {
        Gate::authorize('applications.notify');

        if (! $this->institutionId) {
            return;
        }

        AdmissionLetterTemplate::where('institution_id', $this->institutionId)
            ->where('type', $this->activeType)
            ->delete();

        $this->loadTemplate();

        Flux::toast(
            text: __('Template reset to system default values.'),
            variant: 'info'
        );
    }

    /**
     * Build preview payload array for real-time live preview component.
     *
     * @return array<string, mixed>
     */
    public function getPreviewPayloadProperty(): array
    {
        $institution = $this->institutionId ? Institution::find($this->institutionId) : null;
        $instName = $institution?->name ?? 'Sample Institution';

        $isOffer = $this->activeType === AdmissionLetterTemplate::TYPE_OFFER;
        $ref = $isOffer ? 'STU/2026/0042' : 'PENDING/ENROLL/APP-2026-9812';
        $addresseeName = 'JOHN DOE CHUKWU';
        $programName = 'B.Sc. Nursing Science';
        $sessionLabel = '2026/2027';

        $vars = [
            'applicant_name' => $addresseeName,
            'student_name' => $addresseeName,
            'program_name' => $programName,
            'academic_session' => $sessionLabel,
            'institution_name' => $instName,
            'matric_number' => $isOffer ? 'STU/2026/0042' : '—',
            'application_number' => 'APP-2026-9812',
            'entry_level' => '100L',
            'award_type' => 'Degree',
            'date' => now()->format('jS F, Y'),
            'ref' => $ref,
        ];

        $letterTitle = AdmissionLetterPayload::replacePlaceholders($this->letterTitle, $vars);

        $qrData = implode("\n", [
            $letterTitle,
            'Ref: '.$ref,
            'Candidate: '.$addresseeName,
            'Program: '.$programName,
            'Session: '.$sessionLabel,
            'Institution: '.$instName,
            'Date: '.now()->format('d/m/Y'),
        ]);

        return [
            'institution_name' => $instName,
            'institution_meta' => $institution?->meta,
            'institution_logo_path' => $institution?->logo_path,
            'institution_address' => $institution?->address ?? '123 University Way, Main Campus',
            'institution_email' => $institution?->email ?? 'admissions@institution.edu.ng',
            'institution_phone' => $institution?->phone ?? '+234 800 000 0000',
            'letter_title' => $letterTitle,
            'ref' => $ref,
            'letter_date_formatted' => now()->format('jS F, Y'),
            'addressee_full_name' => $addresseeName,
            'academic_session_label' => $sessionLabel,
            'program_name' => $programName,
            'program_meta_line' => 'Entry Level: 100L &nbsp;|&nbsp; Mode: Full-time &nbsp;|&nbsp; Award: Degree',
            'is_enrolled' => $isOffer,
            'matric_number' => $isOffer ? 'STU/2026/0042' : null,
            'show_fee_paragraph' => ! $isOffer,
            'details_name_label' => $isOffer ? __('Student Name') : __('Applicant Name'),
            'details_name_value' => $addresseeName,
            'application_number' => 'APP-2026-9812',
            'email' => 'johndoe@example.com',
            'phone' => '+234 812 345 6789',
            'session_row_value' => $sessionLabel,
            'qr_data' => $qrData,
            'back_url' => '#',
            'back_label' => '← '.__('Back'),
            'salutation' => AdmissionLetterPayload::replacePlaceholders($this->salutation, $vars),
            'opening_text' => AdmissionLetterPayload::replacePlaceholders($this->openingText, $vars),
            'body_text' => AdmissionLetterPayload::replacePlaceholders($this->bodyText, $vars),
            'conditions_text' => AdmissionLetterPayload::replacePlaceholders($this->conditionsText, $vars),
            'closing_text' => AdmissionLetterPayload::replacePlaceholders($this->closingText, $vars),
            'signatory_title' => AdmissionLetterPayload::replacePlaceholders($this->signatoryTitle, $vars),
            'signatory_subtitle' => AdmissionLetterPayload::replacePlaceholders($this->signatorySubtitle, $vars),
            'show_qr_code' => $this->showQrCode,
            'logo_position' => $this->logoPosition,
            'qr_position' => $this->qrPosition,
        ];
    }

    public function with(): array
    {
        return [
            'institutions' => Institution::query()->orderBy('name')->get(),
            'mustPickInstitution' => Auth::user()->institution_id === null,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Admission Letter Templates') }}</flux:heading>
            <flux:subheading>{{ __('Configure institution-specific text, header branding layout, and QR code placement for admission letters.') }}</flux:subheading>
        </div>

        @if ($mustPickInstitution && count($institutions) > 0)
            <div class="w-72">
                <flux:select wire:model.live="institutionId" label="{{ __('Institution') }}">
                    @foreach ($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-700 gap-4 pb-2">
        <button
            type="button"
            wire:click="setTab('notification')"
            class="pb-2 px-2 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeType === 'notification' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
        >
            <flux:icon name="bell" class="size-4" />
            {{ __('Notification of Provisional Admission') }}
        </button>

        <button
            type="button"
            wire:click="setTab('offer')"
            class="pb-2 px-2 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeType === 'offer' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400' }}"
        >
            <flux:icon name="document-check" class="size-4" />
            {{ __('Offer of Provisional Admission (Official Letter)') }}
        </button>
    </div>

    <div class="space-y-6">
        {{-- Editor Form Card --}}
        <flux:card class="space-y-5">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('Template Content & Layout Editor') }}</flux:heading>
                    @if ($isCustomized)
                        <flux:badge variant="solid" color="indigo" size="sm">{{ __('Customized') }}</flux:badge>
                    @else
                        <flux:badge variant="outline" color="zinc" size="sm">{{ __('System Default') }}</flux:badge>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:modal.trigger name="preview-template-modal">
                        <flux:button variant="subtle" size="sm" icon="eye">
                            {{ __('Preview Template') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="reset-template-modal">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="arrow-path"
                            class="text-red-600 dark:text-red-400 hover:text-red-700"
                        >
                            {{ __('Reset to Default') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <form wire:submit="saveTemplate" class="space-y-6">
                {{-- Header Layout Customization Section --}}
                <div class="p-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg space-y-4">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon name="paint-brush" class="size-4 text-blue-600 dark:text-blue-400" />
                        {{ __('Header Branding & QR Code Layout') }}
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <flux:select wire:model.live="logoPosition" label="{{ __('Institution Logo Alignment') }}">
                            <flux:select.option value="left">{{ __('Left (Default)') }}</flux:select.option>
                            <flux:select.option value="center">{{ __('Center (Top Centered)') }}</flux:select.option>
                            <flux:select.option value="right">{{ __('Right (Top Right)') }}</flux:select.option>
                        </flux:select>

                        <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                            <div>
                                <flux:checkbox
                                    wire:model.live="showQrCode"
                                    label="{{ __('Include Verification QR Code') }}"
                                />
                            </div>

                            <flux:select wire:model.live="qrPosition" label="{{ __('QR Code Placement') }}" :disabled="!$showQrCode">
                                <flux:select.option value="header_right">{{ __('Header Right (Default)') }}</flux:select.option>
                                <flux:select.option value="footer_left">{{ __('Footer Left') }}</flux:select.option>
                                <flux:select.option value="footer_center">{{ __('Footer Center') }}</flux:select.option>
                                <flux:select.option value="footer_right">{{ __('Footer Right') }}</flux:select.option>
                            </flux:select>
                        </div>
                    </div>
                </div>

                {{-- Letter Text Content --}}
                <div class="space-y-4">
                    <flux:input
                        wire:model.live.debounce.300ms="letterTitle"
                        label="{{ __('Document Title') }}"
                        placeholder="{{ __('e.g. NOTIFICATION OF PROVISIONAL ADMISSION') }}"
                        required
                    />

                    <flux:input
                        wire:model.live.debounce.300ms="salutation"
                        label="{{ __('Salutation') }}"
                        placeholder="{{ __('e.g. Dear {applicant_name},') }}"
                    />

                    <flux:textarea
                        wire:model.live.debounce.300ms="openingText"
                        label="{{ __('Opening Paragraph') }}"
                        rows="3"
                        placeholder="{{ __('Introductory text before program details block...') }}"
                    />

                    <flux:textarea
                        wire:model.live.debounce.300ms="bodyText"
                        label="{{ __('Body / Requirement Paragraph') }}"
                        rows="4"
                        placeholder="{{ __('Fee payment instructions or matriculation number notification...') }}"
                    />

                    <flux:textarea
                        wire:model.live.debounce.300ms="conditionsText"
                        label="{{ __('Terms & Credentials Verification') }}"
                        rows="3"
                        placeholder="{{ __('Provisional terms, credentials screening statement...') }}"
                    />

                    <flux:textarea
                        wire:model.live.debounce.300ms="closingText"
                        label="{{ __('Closing Message') }}"
                        rows="2"
                        placeholder="{{ __('Congratulations and closing remarks...') }}"
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:input
                            wire:model.live.debounce.300ms="signatoryTitle"
                            label="{{ __('Signatory Title') }}"
                            placeholder="{{ __('e.g. Registrar') }}"
                        />
                        <flux:input
                            wire:model.live.debounce.300ms="signatorySubtitle"
                            label="{{ __('Signatory Subtitle') }}"
                            placeholder="{{ __('e.g. {institution_name}') }}"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save Template Changes') }}</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Available Placeholders Guide --}}
        <flux:card class="space-y-3 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <flux:icon name="code-bracket" class="size-4 text-blue-600 dark:text-blue-400" />
                <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Dynamic Template Placeholders') }}</h3>
            </div>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">
                {{ __('You can use the following variables in your text fields. They will be automatically replaced with real candidate and institution data when rendered or printed:') }}
            </p>

            <div class="flex flex-wrap gap-2 text-xs font-mono">
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{applicant_name}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{program_name}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{academic_session}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{institution_name}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{matric_number}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{application_number}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{entry_level}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{award_type}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{date}</span>
                <span class="px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded text-blue-700 dark:text-blue-300">{ref}</span>
            </div>
        </flux:card>
    </div>

    {{-- Reset Confirmation Modal --}}
    <flux:modal name="reset-template-modal" variant="filled" class="min-w-[22rem]">
        <form wire:submit.prevent="confirmReset" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reset Template to System Defaults?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This action will remove custom template changes for this institution and restore the default text and layout. This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" x-on:click="$flux.modal('reset-template-modal').close()">{{ __('Confirm Reset') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Template Preview Modal --}}
    <flux:modal name="preview-template-modal" class="max-w-4xl w-full">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Live Template Preview') }}</flux:heading>
                <flux:subheading>{{ __('Realtime rendered printable sheet matching current template inputs.') }}</flux:subheading>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 bg-zinc-100 dark:bg-zinc-950">
                <x-admission-letter.sheet :letter="$this->previewPayload" :show-actions="false" />
            </div>
        </div>
    </flux:modal>
</div>
