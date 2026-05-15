<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicationForm;
use App\Models\Payment;
use App\Models\StudentInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $publicKey;
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->publicKey = config('services.paystack.public_key', '');
        $this->secretKey = config('services.paystack.secret_key', '');
        $this->baseUrl = config('services.paystack.payment_url', 'https://api.paystack.co');
    }

    /**
     * Initialize a checkout session with Paystack.
     */
    public function initializeTransaction(Payment $payment): ?array
    {
        $invoice = $payment->studentInvoice->invoice;
        $student = $payment->studentInvoice->student;

        $payload = [
            'email' => $student->email,
            'amount' => (int) ($payment->amount_paid * 100), // Paystack uses kobo
            'reference' => $payment->reference,
            'callback_url' => route('paystack.return', ['payment_ref' => $payment->reference]),
            'metadata' => [
                'cancel_action' => route('paystack.cancel', ['payment_ref' => $payment->reference]),
                'custom_fields' => [
                    [
                        'display_name' => 'Invoice Title',
                        'variable_name' => 'invoice_title',
                        'value' => $invoice->title,
                    ],
                    [
                        'display_name' => 'Student ID',
                        'variable_name' => 'student_id',
                        'value' => $student->matric_number,
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['status'] === true) {
                return [
                    'checkout_url' => $data['data']['authorization_url'],
                    'gateway_order_no' => $data['data']['reference'],
                ];
            }
            Log::error('Paystack Initialization Error: ' . json_encode($data));
        } else {
            Log::error('Paystack API Request Failed: ' . $response->body());
        }

        return null;
    }

    /**
     * Initialize a checkout session for an Applicant.
     */
    public function initializeApplicationPayment(Applicant $applicant, ApplicationForm $form): ?array
    {
        if (! $applicant->institution->isAdmissionActive()) {
            return null;
        }

        // Generate a unique reference for every attempt
        $reference = $applicant->application_number . '-' . time();

        $applicant->update(['gateway_reference' => $reference]);

        $payload = [
            'email' => $applicant->email,
            'amount' => (int) ($form->amount * 100),
            'reference' => $reference,
            'callback_url' => route('paystack.applicant.return', ['app_number' => $applicant->application_number]),
            'metadata' => [
                'cancel_action' => route('paystack.applicant.cancel', ['app_number' => $applicant->application_number]),
                'custom_fields' => [
                    [
                        'display_name' => 'Application Form',
                        'variable_name' => 'application_form',
                        'value' => $form->name,
                    ],
                    [
                        'display_name' => 'Applicant Ref',
                        'variable_name' => 'applicant_ref',
                        'value' => $applicant->application_number,
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['status'] === true) {
                return [
                    'checkout_url' => $data['data']['authorization_url'],
                    'gateway_order_no' => $data['data']['reference'],
                ];
            }
            Log::error('Paystack Applicant Init Error: ' . json_encode($data));
        } else {
            Log::error('Paystack Applicant API Failed: ' . $response->body());
        }

        return null;
    }

    /**
     * Initialize a checkout session for an Applicant's admission fees.
     */
    public function initializeAdmissionPayment(Applicant $applicant, StudentInvoice $studentInvoice, ?float $amount = null): ?array
    {
        if (! $applicant->institution->isAdmissionActive()) {
            return null;
        }

        $reference = 'ADM-' . $applicant->application_number . '-' . time();
        $balance = $studentInvoice->balance;
        $amountToPay = $amount !== null ? min($amount, $balance) : $balance;

        $payment = Payment::create([
            'institution_id' => $applicant->institution_id,
            'applicant_id' => $applicant->id,
            'student_invoice_id' => $studentInvoice->id,
            'amount_paid' => $amountToPay,
            'payment_method' => 'paystack',
            'payment_type' => 'automated',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $invoice = $studentInvoice->invoice;

        $payload = [
            'email' => $applicant->email,
            'amount' => (int) ($amountToPay * 100),
            'reference' => $reference,
            'callback_url' => route('paystack.applicant.return', ['app_number' => $applicant->application_number, 'type' => 'admission']),
            'metadata' => [
                'cancel_action' => route('paystack.applicant.cancel', ['app_number' => $applicant->application_number, 'type' => 'admission']),
                'custom_fields' => [
                    [
                        'display_name' => 'Invoice Title',
                        'variable_name' => 'invoice_title',
                        'value' => $invoice->title,
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['status'] === true) {
                $payment->update(['gateway_order_no' => $data['data']['reference']]);

                return [
                    'checkout_url' => $data['data']['authorization_url'],
                    'gateway_order_no' => $data['data']['reference'],
                ];
            }
            Log::error('Paystack Admission Init Error: ' . json_encode($data));
        } else {
            Log::error('Paystack Admission API Failed: ' . $response->body());
        }

        $payment->delete();
        return null;
    }

    /**
     * Query payment status from Paystack.
     */
    public function queryStatus(string $reference): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
        ])->get($this->baseUrl . '/transaction/verify/' . rawurlencode($reference));

        if ($response->successful()) {
            $data = $response->json();
            if ($data['status'] === true) {
                return $data['data']; // Returns transaction details including 'status' => 'success'
            }
        }

        return null;
    }

    /**
     * Verify callback signature.
     */
    public function verifyWebhookSignature(string $payloadJson, string $signatureHeader): bool
    {
        $calculatedSignature = hash_hmac('sha512', $payloadJson, $this->secretKey);
        return hash_equals($calculatedSignature, $signatureHeader);
    }
}
