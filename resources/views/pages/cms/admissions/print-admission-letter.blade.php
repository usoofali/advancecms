<?php

use App\Models\Applicant;
use App\Models\Student;
use App\ViewModels\AdmissionLetterPayload;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Letter')] #[Layout('layouts.guest')] class extends Component
{
    public Applicant $applicant;

    public ?Student $student = null;

    /** @var array<string, mixed> */
    public array $letter = [];

    public function mount(Applicant $applicant): void
    {
        if (auth()->check() && auth()->user()->hasRole('Student') && auth()->user()->email !== $applicant->email) {
            abort(403, 'Unauthorized. You can only view your own admission letter.');
        }

        if ($applicant->admission_status !== 'admitted') {
            abort(403, 'Applicant has not been admitted.');
        }

        $this->applicant = $applicant->load(['institution', 'program', 'applicationForm.academicSession']);
        
        $studentInvoice = $this->applicant->studentInvoices()->latest()->first();
        if (!$this->applicant->enrolled_at && $studentInvoice && in_array($studentInvoice->status, ['paid', 'partial'])) {
            app(\App\Services\AdmissionService::class)->enrollApplicant($this->applicant);
            $this->applicant->refresh();
        }

        if (!$this->applicant->enrolled_at) {
            abort(403, 'You must pay your admission fees (minimum 50%) to officially enroll and access your admission letter.');
        }

        $this->student = Student::where('email', $applicant->email)->first();
        $this->student->load(['institution', 'program.department']);
        $this->letter = AdmissionLetterPayload::fromStudent($this->student);
        $this->letter['back_url'] = route('applicant.portal', ['application_number' => $applicant->application_number]);
        $this->letter['back_label'] = '← ' . __('Back to Portal');
    }
}; ?>

<x-admission-letter.sheet :letter="$letter" />
