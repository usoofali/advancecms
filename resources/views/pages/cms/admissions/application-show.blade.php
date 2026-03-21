<?php

use App\Models\Applicant;
use App\Models\ApplicantCredential;
use App\Services\AdmissionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Review Application')] #[Layout('layouts.app')] class extends Component
{
    public Applicant $applicant;
    public ?ApplicantCredential $credentials = null;
    public string $rejectionReason = '';

    public function mount(Applicant $applicant): void
    {
        $this->applicant = $applicant->load(['institution', 'program', 'applicationForm']);
        $this->credentials = ApplicantCredential::where('applicant_id', $this->applicant->id)->first();
    }

    public function admitApplicant(AdmissionService $admissionService)
    {
        try {
            $admissionService->admit($this->applicant);

            session()->flash('success', 'Applicant admitted successfully! Admission letter and fees invoice have been generated.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to admit applicant: '.$e->getMessage());
        }

        return redirect()->route('cms.admissions.index');
    }

    public function rejectApplicant(AdmissionService $admissionService)
    {
        $this->validate([
            'rejectionReason' => 'required|string|max:500',
        ]);

        try {
            $admissionService->reject($this->applicant, $this->rejectionReason);

            session()->flash('success', 'Applicant rejected and notified with reason.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reject applicant: '.$e->getMessage());
        }

        return redirect()->route('cms.admissions.index');
    }

    public function enrollApplicant(AdmissionService $admissionService)
    {
        if (! auth()->user()->can('enroll_applicants')) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'You are not authorized to perform enrollment.']);
            $this->js('$flux.modal("enroll-modal").close()');
            return;
        }

        try {
            $student = $admissionService->enrollApplicant($this->applicant);
            $this->js('$flux.modal("enroll-modal").close()');
            $this->dispatch('notify', ['type' => 'success', 'message' => "Applicant enrolled successfully! Matric number: {$student->matric_number}"]);
        } catch (\Exception $e) {
            $this->js('$flux.modal("enroll-modal").close()');
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to enroll applicant: '.$e->getMessage()]);
        }

        $this->applicant = $this->applicant->fresh(['institution', 'program', 'applicationForm']);
    }

    public function with(): array
    {
        $admissionInvoice = $this->applicant->studentInvoices()->latest()->first();
        $feesPaid = $admissionInvoice ? $admissionInvoice->amount_paid : 0;
        $feesTotal = $admissionInvoice ? $admissionInvoice->total_amount : 0;
        $feePercent = $feesTotal > 0 ? round(($feesPaid / $feesTotal) * 100, 1) : 0;
        $meetsEnrollmentThreshold = $feesTotal > 0 && $feePercent >= 50;

        return [
            'canApprove' => auth()->user()->can('approve_admissions'),
            'canEnroll' => auth()->user()->can('enroll_applicants'),
            'admissionInvoice' => $admissionInvoice,
            'feesPaid' => $feesPaid,
            'feesTotal' => $feesTotal,
            'feePercent' => $feePercent,
            'meetsEnrollmentThreshold' => $meetsEnrollmentThreshold,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-4">
            <flux:button variant="subtle" icon="arrow-left" :href="route('cms.admissions.index')" class="hidden sm:flex" wire:navigate />
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl">{{ __('Review Application') }}</flux:heading>
                    <flux:badge color="{{ match($applicant->admission_status) {
                        'admitted' => 'success',
                        'under_review' => 'blue',
                        'rejected' => 'danger',
                        default => 'zinc',
                    } }}">
                        {{ ucfirst(str_replace('_', ' ', $applicant->admission_status)) }}
                    </flux:badge>
                </div>
                <flux:subheading>{{ __('Application #') }}{{ $applicant->application_number }} - {{ $applicant->program?->name }}</flux:subheading>
            </div>
        </div>
        
        @if($applicant->enrolled_at)
        <div class="flex items-center gap-2">
            <flux:badge color="green" size="lg" icon="check-circle">{{ __('Enrolled') }}</flux:badge>
            <flux:button variant="primary" icon="printer" href="{{ route('applicant.admission-letter', $applicant) }}" target="_blank">{{ __('Print Admission Letter') }}</flux:button>
        </div>
        @elseif($applicant->admission_status === 'admitted')
        <div class="flex items-center gap-2">
            <flux:button variant="primary" icon="printer" href="{{ route('applicant.admission-letter', $applicant) }}" target="_blank">{{ __('Print Admission Letter') }}</flux:button>
            @if($canEnroll)
                @if($meetsEnrollmentThreshold)
                    <flux:modal.trigger name="enroll-modal">
                        <flux:button variant="primary" icon="academic-cap">{{ __('Enroll as Student') }}</flux:button>
                    </flux:modal.trigger>
                @else
                    <flux:button variant="primary" icon="academic-cap" disabled title="{{ __('Applicant must pay at least 50% of the admission fee before enrollment.') }}">{{ __('Enroll as Student') }}</flux:button>
                @endif
            @endif
        </div>
        @elseif($canApprove && $applicant->admission_status === 'under_review')
        <div class="flex items-center gap-2">
            <flux:modal.trigger name="reject-modal">
                <flux:button variant="danger" icon="x-mark">{{ __('Reject') }}</flux:button>
            </flux:modal.trigger>
            
            <flux:modal.trigger name="admit-modal">
                <flux:button variant="primary" icon="check">{{ __('Admit Applicant') }}</flux:button>
            </flux:modal.trigger>
        </div>
        @endif
    </div>

    <!-- Enroll Modal -->
    <flux:modal name="enroll-modal" class="md:w-96">
        <form wire:submit="enrollApplicant" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Enroll as Student') }}</flux:heading>
                <flux:subheading>{!! __('You are about to enroll <strong>:name</strong> into <strong>:program</strong>.', ['name' => $applicant->full_name, 'program' => $applicant->program->name]) !!}</flux:subheading>
            </div>

            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-200 text-sm rounded-lg border border-blue-200 dark:border-blue-800 space-y-1">
                <div class="font-semibold">{{ __('Fees Summary') }}</div>
                <div>{{ __('Total:') }} <strong>₦{{ number_format($feesTotal, 2) }}</strong></div>
                <div>{{ __('Paid:') }} <strong>₦{{ number_format($feesPaid, 2) }}</strong> ({{ $feePercent }}%)</div>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm rounded-lg border border-amber-200 dark:border-amber-800">
                <flux:icon.exclamation-triangle class="w-5 h-5 inline-block mr-1 text-amber-600 dark:text-amber-400" />
                {{ __('This will create a student record and assign a matric number. This action cannot be undone.') }}
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Confirm Enrollment') }}</span>
                    <span wire:loading>{{ __('Enrolling...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Reject Modal -->
    <flux:modal name="reject-modal" class="md:w-96">
        <form wire:submit="rejectApplicant" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject Applicant') }}</flux:heading>
                <flux:subheading>{{ __('Please provide a reason for rejecting this application.') }}</flux:subheading>
            </div>

            <flux:textarea wire:model="rejectionReason" label="{{ __('Reason for Rejection') }}" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Confirm Rejection') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Admit Modal -->
    <flux:modal name="admit-modal" class="md:w-96">
        <form wire:submit="admitApplicant" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Admit Applicant') }}</flux:heading>
                <flux:subheading>{!! __('Are you sure you want to admit <strong>:name</strong> to <strong>:program</strong>?', ['name' => $applicant->full_name, 'program' => $applicant->program->name]) !!}</flux:subheading>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm rounded-lg border border-amber-200 dark:border-amber-800">
                <flux:icon.exclamation-triangle class="w-5 h-5 inline-block mr-1 text-amber-600 dark:text-amber-400" />
                This action will update the admission status to "admitted", generate the admission fees invoice, and notify the applicant via email. <strong>Note: Student enrollment and matric number generation will only happen after the applicant pays the required fees.</strong>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Confirm Admission') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Applicant Details -->
        <div class="md:col-span-1 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Personal Details') }}</flux:heading>
                
                <div class="space-y-4">
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Full Name') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->full_name }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Email') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->email }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Phone') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->phone }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Institution') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->institution->name }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Program') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->program->name }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Application Form') }}</div>
                        <div class="text-zinc-900 dark:text-zinc-100">{{ $applicant->applicationForm->name }} ({{ config('app.currency', 'NGN') }} {{ number_format($applicant->applicationForm->amount, 2) }})</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Payment Status') }}</div>
                        <div class="font-medium text-{{ $applicant->payment_status === 'paid' ? 'green' : 'amber' }}-600">
                            {{ ucfirst($applicant->payment_status) }}
                        </div>
                    </div>
                </div>
            </flux:card>

            @if($applicant->admission_status === 'admitted')
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Enrollment Status') }}</flux:heading>
                <div class="space-y-4">
                    <div>
                        <div class="text-sm font-medium text-zinc-500">{{ __('Status') }}</div>
                        @if($applicant->enrolled_at)
                            <flux:badge color="green">{{ __('Enrolled') }}</flux:badge>
                            <div class="mt-1 text-xs text-zinc-500">{{ __('Enrolled on') }} {{ $applicant->enrolled_at->format('d M Y') }}</div>
                        @else
                            <flux:badge color="amber">{{ __('Pending Enrollment') }}</flux:badge>
                        @endif
                    </div>

                    @if($admissionInvoice)
                    <div>
                        <div class="text-sm font-medium text-zinc-500 mb-2">{{ __('Admission Fees') }}</div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 mb-1">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($feePercent, 100) }}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-zinc-500">
                            <span>₦{{ number_format($feesPaid, 2) }} paid</span>
                            <span>{{ $feePercent }}%</span>
                        </div>
                        <div class="text-xs text-zinc-500 mt-1">{{ __('Total:') }} ₦{{ number_format($feesTotal, 2) }}</div>
                        @if(!$meetsEnrollmentThreshold && !$applicant->enrolled_at)
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                <flux:icon.information-circle class="w-3.5 h-3.5 inline-block" />
                                {{ __('At least 50% must be paid to enable enrollment.') }}
                            </p>
                        @endif
                    </div>
                    @else
                    <div class="text-sm text-zinc-400 italic">{{ __('No admission invoice found.') }}</div>
                    @endif
                </div>
            </flux:card>
            @endif
        </div>

        <!-- Credentials View -->
        <div class="md:col-span-2 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Academic Credentials (O-Level)') }}</flux:heading>
                
                @if($credentials)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8">
                        <div>
                            <flux:heading size="sm" class="mb-3 text-zinc-500 uppercase tracking-widest font-semibold">{{ __('First Sitting') }}</flux:heading>
                            <div class="p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Type:') }}</span>
                                    <span class="font-medium">{{ $credentials->sitting_1_exam_type }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Number:') }}</span>
                                    <span class="font-medium font-mono">{{ $credentials->sitting_1_exam_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Year:') }}</span>
                                    <span class="font-medium">{{ $credentials->sitting_1_exam_year }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <flux:heading size="sm" class="mb-3 text-zinc-500 uppercase tracking-widest font-semibold">{{ __('Second Sitting') }}</flux:heading>
                            @if($credentials->sitting_2_exam_type)
                            <div class="p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Type:') }}</span>
                                    <span class="font-medium">{{ $credentials->sitting_2_exam_type }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Number:') }}</span>
                                    <span class="font-medium font-mono">{{ $credentials->sitting_2_exam_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">{{ __('Exam Year:') }}</span>
                                    <span class="font-medium">{{ $credentials->sitting_2_exam_year }}</span>
                                </div>
                            </div>
                            @else
                                <div class="p-4 border border-dashed border-zinc-200 dark:border-zinc-700 rounded-lg text-center text-zinc-400 text-sm">
                                    {{ __('No second sitting provided.') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <flux:heading size="sm" class="mb-3 text-zinc-500 uppercase tracking-widest font-semibold">{{ __('Core Subjects Review') }}</flux:heading>
                    <div class="overflow-hidden bg-white dark:bg-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800 rounded-lg">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Subject') }}</th>
                                    <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Grade') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                @php
                                    $subjects = [
                                        'English Language' => $credentials->subject_english,
                                        'Mathematics' => $credentials->subject_mathematics,
                                        'Biology' => $credentials->subject_biology,
                                        'Chemistry' => $credentials->subject_chemistry,
                                        'Physics' => $credentials->subject_physics,
                                    ];
                                @endphp
                                @foreach($subjects as $subject => $grade)
                                <tr>
                                    <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $subject }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-sm font-bold text-center {{ in_array($grade, ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'A']) ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $grade }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-8">
                        <flux:heading size="sm" class="mb-3 text-zinc-500 uppercase tracking-widest font-semibold">{{ __('Attached Documents') }}</flux:heading>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @if($credentials->primary_document_path)
                            <a href="{{ Storage::url($credentials->primary_document_path) }}" target="_blank" class="flex items-center gap-3 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <flux:icon.paper-clip class="w-5 h-5 text-zinc-400" />
                                <div class="flex-1">
                                    <div class="text-sm font-medium">{{ __('Primary Document') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('O-Level Certificate / Statement') }}</div>
                                </div>
                                <flux:icon.eye class="w-4 h-4 text-zinc-400" />
                            </a>
                            @endif

                            @if($credentials->secondary_document_path)
                            <a href="{{ Storage::url($credentials->secondary_document_path) }}" target="_blank" class="flex items-center gap-3 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                <flux:icon.paper-clip class="w-5 h-5 text-zinc-400" />
                                <div class="flex-1">
                                    <div class="text-sm font-medium">{{ __('Secondary Document') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('Optional Supporting Doc') }}</div>
                                </div>
                                <flux:icon.eye class="w-4 h-4 text-zinc-400" />
                            </a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="p-12 text-center">
                        <flux:icon.document-text class="w-12 h-12 text-zinc-300 mx-auto mb-4" />
                        <h3 class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No Credentials Submitted') }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('This applicant has not provided their O-Level results yet.') }}</p>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
