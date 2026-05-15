<?php

use App\Models\Applicant;
use App\Models\Student;
use App\ViewModels\AdmissionLetterPayload;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admission Notification')] #[Layout('layouts.guest')] class extends Component
{
    public Applicant $applicant;

    /** @var array<string, mixed> */
    public array $letter = [];

    public function mount(Applicant $applicant): void
    {
        if ($applicant->admission_status !== 'admitted') {
            abort(403, 'Applicant has not been admitted.');
        }

        $this->applicant = $applicant->load(['institution', 'program', 'applicationForm.academicSession']);
        
        $this->letter = AdmissionLetterPayload::fromApplicant($this->applicant, null);
        $this->letter['back_url'] = route('applicant.portal', ['application_number' => $applicant->application_number]);
        $this->letter['back_label'] = '← ' . __('Back to Portal');
    }
}; ?>

<x-admission-letter.sheet :letter="$letter" />
