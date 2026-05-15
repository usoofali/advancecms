<?php

namespace App\Livewire\Pages\Admissions;

use App\Models\Applicant;
use App\Models\ApplicationForm;
use App\Models\Institution;
use App\Models\Program;
use App\Notifications\ApplicationSubmittedNotification;
use App\Services\PaystackService;
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

    public string $mode = 'apply';

    public string $resumeEmail = '';

    public string $resumePhone = '';

    /** Whether to show the email confirmation screen. */
    public bool $showConfirmation = false;

    public function mount()
    {
        //
    }

    public function setMode(string $newMode)
    {
        $this->mode = $newMode;
        $this->showConfirmation = false;
        $this->resetValidation();
    }

    public function selectForm(int $formId)
    {
        $form = ApplicationForm::findOrFail($formId);
        $this->institution_id = $form->institution_id;
        $this->application_form_id = $formId;
        $this->resetValidation();
        $this->js('$flux.modal("purchase-form").show();');
    }

    /**
     * Step 1 – validate, create the applicant record, send the portal-link email,
     * then show the "check your email" confirmation before redirecting to Paystack.
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
        if (! $institution->isAdmissionActive()) {
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

        $applicant = Applicant::create([
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

        // Send Email Notification
        $applicant->notify(new ApplicationSubmittedNotification($applicant));

        $this->applicant = $applicant;
        $this->showConfirmation = true;
        
        $this->js('$flux.modal("purchase-form").close();');
    }

    public function resumeApplication()
    {
        $this->validate([
            'resumeEmail' => ['required', 'email'],
            'resumePhone' => ['required'],
        ]);

        $applicant = Applicant::where('email', $this->resumeEmail)
            ->where('phone', $this->resumePhone)
            ->first();

        if ($applicant) {
            return redirect()->route('applicant.portal', ['application_number' => $applicant->application_number]);
        }

        $this->addError('resumeEmail', 'No application found with this email and phone combination.');
    }

    /**
     * Step 2 – applicant has acknowledged the email notice; now redirect to OPay.
     */
    public function proceedToPayment(PaystackService $paystackService)
    {
        if (! $this->applicant) {
            $this->showConfirmation = false;

            return;
        }

        $form = ApplicationForm::findOrFail($this->applicant->application_form_id);
        $initData = $paystackService->initializeApplicationPayment($this->applicant, $form);

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
            'forms' => ApplicationForm::with(['academicSession', 'institution'])
                ->where('is_active', true)
                ->get()
                ->filter(fn($form) => $form->institution?->isAdmissionActive()),
            'programs' => $this->institution_id
                ? Program::where('institution_id', $this->institution_id)->get()
                : [],
        ])->layout('layouts.guest');
    }
}
