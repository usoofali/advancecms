<?php

namespace App\Livewire\Pages\Admissions;

use App\Models\Applicant;
use App\Models\ApplicantCredential;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\OPayService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ApplicantPortal extends Component
{
    use WithFileUploads;

    public string $application_number;

    public ?Applicant $applicant = null;

    public ?float $partial_payment_amount = null;

    public function mount($application_number)
    {
        $this->application_number = $application_number;
        $this->applicant = Applicant::where('application_number', $application_number)->firstOrFail();

        $this->ensureApplicantHasReceipt();
    }

    protected function ensureApplicantHasReceipt(): void
    {
        if ($this->applicant->payment_status === 'paid' && ! $this->applicant->payments()->where('status', 'success')->exists()) {
            // Create a manual payment record for legacy/manually marked paid applicants
            $payment = Payment::create([
                'institution_id' => $this->applicant->institution_id,
                'applicant_id' => $this->applicant->id,
                'amount_paid' => $this->applicant->applicationForm->amount,
                'payment_method' => 'cash', // Fallback for manual
                'payment_type' => 'manual',
                'reference' => $this->applicant->application_number,
                'status' => 'success',
            ]);

            Receipt::create([
                'institution_id' => $this->applicant->institution_id,
                'payment_id' => $payment->id,
                'receipt_number' => 'APP-REC-'.strtoupper(Str::random(10)),
                'issued_at' => now(),
            ]);

            $this->applicant->refresh();
        }
    }

    public function retryPayment(OPayService $opayService)
    {
        if (! $this->applicant->institution->isAdmissionActive()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Admissions and payments are now closed for this institution.',
            ]);

            return null;
        }

        $initData = $opayService->initializeApplicationPayment($this->applicant, $this->applicant->applicationForm);

        if ($initData && isset($initData['checkout_url'])) {
            return redirect()->away($initData['checkout_url']);
        }

        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Failed to initialize payment gateway. Please try again.',
        ]);

        return null;
    }

    public function payAdmissionFee(OPayService $opayService)
    {
        if (! $this->applicant->institution->isAdmissionActive()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Admissions and payments are now closed for this institution.',
            ]);

            return null;
        }

        $studentInvoice = $this->applicant->studentInvoices()->latest()->first();

        if (! $studentInvoice || in_array($studentInvoice->status, ['paid'])) {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'No outstanding admission invoice found.',
            ]);

            return null;
        }

        $minAmount = $studentInvoice->total_amount * 0.5;
        $balance = $studentInvoice->balance;

        $amountToPay = $this->partial_payment_amount ? (float) $this->partial_payment_amount : $balance;

        if ($amountToPay < $minAmount && $amountToPay < $balance) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Minimum payment is 50% of the total invoice (₦'.number_format($minAmount, 2).').',
            ]);

            return null;
        }

        if ($amountToPay > $balance) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Amount exceeds the remaining balance (₦'.number_format($balance, 2).').',
            ]);

            return null;
        }

        // Clean up any existing abandoned pending payments to prevent duplicates
        Payment::where('student_invoice_id', $studentInvoice->id)
            ->where('applicant_id', $this->applicant->id)
            ->where('status', 'pending')
            ->where('payment_method', 'opay')
            ->delete();

        $initData = $opayService->initializeAdmissionPayment($this->applicant, $studentInvoice, $amountToPay);

        if ($initData && isset($initData['checkout_url'])) {
            return redirect()->away($initData['checkout_url']);
        }

        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Failed to initialize payment gateway. Please try again.',
        ]);

        return null;
    }

    public string $sitting_1_exam_type = '';

    public string $sitting_1_exam_number = '';

    public string $sitting_1_exam_year = '';

    public string $sitting_2_exam_type = '';

    public string $sitting_2_exam_number = '';

    public string $sitting_2_exam_year = '';

    public string $subject_mathematics = '';

    public string $subject_english = '';

    public string $subject_biology = '';

    public string $subject_chemistry = '';

    public string $subject_physics = '';

    public $primary_document;

    public $secondary_document;

    public $retrainee_document;

    public function submitCredentials()
    {
        if (! $this->applicant->institution->isAdmissionActive()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'The deadline for credential submission has passed.',
            ]);

            return;
        }

        $examTypes = ['NECO', 'WAEC', 'NABTEB', 'NBAIS'];
        $validGrades = ['A', 'A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];

        $rules = [
            'sitting_1_exam_type' => ['required', 'string', 'in:'.implode(',', $examTypes)],
            'sitting_1_exam_number' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! $this->sitting_1_exam_type) {
                    return;
                }
                $regex = $this->getExamRegex($this->sitting_1_exam_type);
                if (! preg_match($regex, $value)) {
                    $fail(__('The :attribute format is invalid for :type.', ['type' => $this->sitting_1_exam_type]));
                }
            }],
            'sitting_1_exam_year' => ['required', 'numeric', 'digits:4', 'min:1900', 'max:'.date('Y')],

            // Sitting 2
            'sitting_2_exam_type' => ['nullable', 'string', 'in:'.implode(',', $examTypes)],
            'sitting_2_exam_number' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (! $this->sitting_2_exam_type) {
                    return;
                }
                $regex = $this->getExamRegex($this->sitting_2_exam_type);
                if (! preg_match($regex, $value)) {
                    $fail(__('The :attribute format is invalid for :type.', ['type' => $this->sitting_2_exam_type]));
                }
            }],
            'sitting_2_exam_year' => ['nullable', 'numeric', 'digits:4', 'min:1900', 'max:'.date('Y')],

            // Subjects
            'subject_english' => ['required', 'string', 'in:'.implode(',', $validGrades)],
            'subject_mathematics' => ['required', 'string', 'in:'.implode(',', $validGrades)],
            'subject_biology' => ['required', 'string', 'in:'.implode(',', $validGrades)],
            'subject_chemistry' => ['required', 'string', 'in:'.implode(',', $validGrades)],
            'subject_physics' => ['required', 'string', 'in:'.implode(',', $validGrades)],

            // Documents
            'primary_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'secondary_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'retrainee_document' => [
                $this->applicant->applicationForm->category === 'retrainee' ? 'required' : 'nullable',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048',
            ],
        ];

        $this->validate($rules);

        // Upload documents
        $primaryPath = $this->primary_document->store('credentials', 'public');
        $secondaryPath = $this->secondary_document ? $this->secondary_document->store('credentials', 'public') : null;
        $retraineePath = $this->retrainee_document ? $this->retrainee_document->store('credentials', 'public') : null;

        // Save credentials
        ApplicantCredential::updateOrCreate(
            ['applicant_id' => $this->applicant->id],
            [
                'sitting_1_exam_type' => $this->sitting_1_exam_type,
                'sitting_1_exam_number' => $this->sitting_1_exam_number,
                'sitting_1_exam_year' => $this->sitting_1_exam_year,
                'sitting_2_exam_type' => $this->sitting_2_exam_type ?: null,
                'sitting_2_exam_number' => $this->sitting_2_exam_number ?: null,
                'sitting_2_exam_year' => $this->sitting_2_exam_year ?: null,
                'subject_english' => $this->subject_english,
                'subject_mathematics' => $this->subject_mathematics,
                'subject_biology' => $this->subject_biology,
                'subject_chemistry' => $this->subject_chemistry,
                'subject_physics' => $this->subject_physics,
                'primary_document_path' => $primaryPath,
                'secondary_document_path' => $secondaryPath,
                'retrainee_document_path' => $retraineePath,
            ]
        );

        // Update applicant status
        $this->applicant->update(['admission_status' => 'under_review']);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Credentials submitted successfully! Your application is now under review.',
        ]);
    }

    protected function getExamRegex(string $type): string
    {
        return match ($type) {
            'NECO' => '/^\d{10}(\d{2})?[A-Za-z]{2}$/',
            'WAEC' => '/^\d{10}$/',
            'NABTEB' => '/^\d{8}$/',
            'NBAIS' => '/^\d{6,10}$/',
            default => '/.*/',
        };
    }

    public function render()
    {
        return view('livewire.pages.admissions.applicant-portal')->layout('layouts.guest');
    }
}
