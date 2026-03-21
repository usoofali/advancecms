<?php

namespace App\Livewire\Pages\Admissions;

use App\Models\Applicant;
use App\Models\ApplicationForm;
use App\Models\Institution;
use App\Models\Program;
use App\Notifications\ApplicationSubmittedNotification;
use App\Services\OPayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class Apply extends Component
{
    public $institution_id;

    public $application_form_id;

    public $program_id;

    public $full_name;

    public $email;

    public $phone;

    /** Holds the just-created applicant so we can proceed to payment in step 2. */
    public ?Applicant $applicant = null;

    /** Whether to show the email confirmation screen. */
    public bool $showConfirmation = false;

    /**
     * Step 1 – validate, create the applicant record, send the portal-link email,
     * then show the "check your email" confirmation before redirecting to OPay.
     */
    public function submit(): void
    {
        $this->validate([
            'institution_id' => 'required|exists:institutions,id',
            'program_id' => 'required|exists:programs,id',
            'application_form_id' => 'required|exists:application_forms,id',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $institution = Institution::findOrFail($this->institution_id);
        if (!$institution->isAdmissionActive()) {
            $this->addError('institution_id', 'Admissions are currently closed for this institution.');
            return;
        }

        // If an application already exists for this email, show an informative error
        $existing = Applicant::where('email', $this->email)->latest()->first();

        if ($existing) {
            $this->addError('email', 'An application already exists for this email (Ref: '.$existing->application_number.'). Please check your inbox for the portal access link.');

            return;
        }

        // Block emails that already belong to an enrolled user (student/staff) to prevent enrollment conflicts
        if (DB::table('users')->where('email', $this->email)->exists()) {
            $this->addError('email', 'This email address is already registered as a system user. Please use a different email or contact the admissions office.');

            return;
        }

        $this->applicant = Applicant::create([
            'application_number' => 'APP-'.date('Y').'-'.strtoupper(Str::random(6)),
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'institution_id' => $this->institution_id,
            'program_id' => $this->program_id,
            'application_form_id' => $this->application_form_id,
            'payment_status' => 'pending',
            'admission_status' => 'pending',
        ]);

        // Send the portal-access notification
        $this->applicant->notify(new ApplicationSubmittedNotification($this->applicant));

        // Show the email-confirmation screen (step 2)
        $this->showConfirmation = true;
    }

    /**
     * Step 2 – applicant has acknowledged the email notice; now redirect to OPay.
     */
    public function proceedToPayment(OPayService $opayService)
    {
        if (! $this->applicant) {
            $this->showConfirmation = false;

            return;
        }

        $form = ApplicationForm::findOrFail($this->applicant->application_form_id);
        $initData = $opayService->initializeApplicationPayment($this->applicant, $form);

        if ($initData && isset($initData['checkout_url'])) {
            return redirect()->away($initData['checkout_url']);
        }

        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Failed to initialize the payment gateway. Please try again.',
        ]);

        return null;
    }

    public function render()
    {
        return view('livewire.pages.admissions.apply', [
            'institutions' => Institution::all(),
            'forms' => $this->institution_id
                ? ApplicationForm::where('institution_id', $this->institution_id)->where('is_active', true)->get()
                : [],
            'programs' => $this->institution_id
                ? Program::where('institution_id', $this->institution_id)->get()
                : [],
        ])->layout('layouts.guest');
    }
}
