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
        Gate::authorize('applications.print_letter');

        if (auth()->user()->hasRole('Student') && auth()->user()->email !== $applicant->email) {
            abort(403, 'Unauthorized. You can only view your own admission letter.');
        }

        if ($applicant->admission_status !== 'admitted') {
            abort(403, 'Applicant has not been admitted.');
        }

        $this->applicant = $applicant->load(['institution', 'program', 'applicationForm.academicSession']);
        $this->student = Student::where('email', $applicant->email)->first();
        $this->letter = AdmissionLetterPayload::fromApplicant($this->applicant, $this->student);
    }
}; ?>

<x-admission-letter.sheet :letter="$letter" />
