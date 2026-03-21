<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicationForm;
use App\Models\Payment;
use App\Models\StudentInvoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OPayService
{
    protected string $merchantId;

    protected string $publicKey;

    protected string $secretKey;

    protected string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.opay.merchant_id');
        $this->publicKey = config('services.opay.public_key');
        $this->secretKey = config('services.opay.secret_key');

        $mode = config('services.opay.mode', 'sandbox');
        $this->baseUrl = $mode === 'live'
            ? 'https://liveapi.opaycheckout.com/api/v1/international'
            : 'https://testapi.opaycheckout.com/api/v1/international';
    }

    /**
     * Initialize a checkout session with OPay.
     */
    public function initializeTransaction(Payment $payment): ?array
    {
        $invoice = $payment->studentInvoice->invoice;
        $student = $payment->studentInvoice->student;

        $payload = [
            'country' => 'NG',
            'reference' => $payment->reference,
            'amount' => [
                'total' => (int) ($payment->amount_paid * 100), // OPay uses kobo/cents
                'currency' => 'NGN',
            ],
            'returnUrl' => route('opay.return', ['reference' => $payment->reference]),
            'callbackUrl' => route('opay.callback'),
            'cancelUrl' => route('opay.cancel', ['reference' => $payment->reference]),
            'displayName' => config('app.name'),
            'product' => [
                'name' => $invoice->title,
                'description' => "Tuition/Fee payment for {$invoice->title} - Session: {$invoice->academicSession?->name}",
            ],
            'userInfo' => [
                'userEmail' => $student->email,
                'userId' => (string) $student->matric_number,
                'userMobile' => $student->phone,
                'userName' => $student->full_name,
            ],
            'expireAt' => 30, // 30 minutes
            'customerVisitSource' => 'WEB',
            'evokeOpay' => true,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->publicKey,
            'MerchantId' => $this->merchantId,
        ])->post($this->baseUrl.'/cashier/create', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['code'] === '00000') {
                return [
                    'checkout_url' => $data['data']['cashierUrl'],
                    'gateway_order_no' => $data['data']['orderNo'],
                ];
            }

            Log::error('OPay Initialization Error: '.json_encode($data));
        } else {
            Log::error('OPay API Request Failed: '.$response->body());
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

        // Generate a unique reference for every attempt to avoid OPay 'order already exist' errors
        $reference = $applicant->application_number.'-'.time();

        // Store this reference on the applicant so the callback can match it
        $applicant->update(['gateway_reference' => $reference]);

        $payload = [
            'country' => 'NG',
            'reference' => $reference,
            'amount' => [
                'total' => (int) ($form->amount * 100), // OPay uses kobo/cents
                'currency' => 'NGN',
            ],
            // Return URL uses application_number so the controller can still find the applicant
            'returnUrl' => route('opay.applicant.return', ['reference' => $applicant->application_number]),
            'callbackUrl' => route('opay.callback'),
            'cancelUrl' => route('opay.applicant.cancel', ['reference' => $applicant->application_number]),
            'displayName' => config('app.name'),
            'product' => [
                'name' => $form->name,
                'description' => "Application Form Fee: {$form->name}",
            ],
            'userInfo' => [
                'userEmail' => $applicant->email,
                'userId' => (string) $applicant->application_number,
                'userMobile' => $applicant->phone,
                'userName' => $applicant->full_name,
            ],
            'expireAt' => 30, // 30 minutes
            'customerVisitSource' => 'WEB',
            'evokeOpay' => true,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->publicKey,
            'MerchantId' => $this->merchantId,
        ])->post($this->baseUrl.'/cashier/create', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['code'] === '00000') {
                return [
                    'checkout_url' => $data['data']['cashierUrl'],
                    'gateway_order_no' => $data['data']['orderNo'],
                ];
            }

            Log::error('OPay Applicant Init Error: '.json_encode($data));
        } else {
            Log::error('OPay Applicant API Failed: '.$response->body());
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

        $reference = 'ADM-'.$applicant->application_number.'-'.time();
        $balance = $studentInvoice->balance;
        $amountToPay = $amount !== null ? min($amount, $balance) : $balance;

        $payment = Payment::create([
            'institution_id' => $applicant->institution_id,
            'applicant_id' => $applicant->id,
            'student_invoice_id' => $studentInvoice->id,
            'amount_paid' => $amountToPay,
            'payment_method' => 'opay',
            'payment_type' => 'automated',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $invoice = $studentInvoice->invoice;

        $payload = [
            'country' => 'NG',
            'reference' => $reference,
            'amount' => [
                'total' => (int) ($amountToPay * 100),
                'currency' => 'NGN',
            ],
            'returnUrl' => route('opay.applicant.return', ['reference' => $applicant->application_number]),
            'callbackUrl' => route('opay.callback'),
            'cancelUrl' => route('opay.applicant.cancel', ['reference' => $applicant->application_number]),
            'displayName' => config('app.name'),
            'product' => [
                'name' => $invoice->title,
                'description' => "Admission Fee: {$invoice->title}",
            ],
            'userInfo' => [
                'userEmail' => $applicant->email,
                'userId' => (string) $applicant->application_number,
                'userMobile' => $applicant->phone,
                'userName' => $applicant->full_name,
            ],
            'expireAt' => 30,
            'customerVisitSource' => 'WEB',
            'evokeOpay' => true,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->publicKey,
            'MerchantId' => $this->merchantId,
        ])->post($this->baseUrl.'/cashier/create', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['code'] === '00000') {
                $payment->update(['gateway_order_no' => $data['data']['orderNo']]);

                return [
                    'checkout_url' => $data['data']['cashierUrl'],
                    'gateway_order_no' => $data['data']['orderNo'],
                ];
            }

            Log::error('OPay Admission Init Error: '.json_encode($data));
        } else {
            Log::error('OPay Admission API Failed: '.$response->body());
        }

        $payment->delete();

        return null;
    }

    /**
     * Query payment status from OPay.
     */
    public function queryStatus(string $reference): ?array
    {
        $payload = [
            'reference' => $reference,
            'country' => 'NG',
        ];

        ksort($payload);
        $signature = $this->generateSignature($payload);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$signature,
            'MerchantId' => $this->merchantId,
        ])->post($this->baseUrl.'/cashier/status', $payload);

        if ($response->successful()) {
            $data = $response->json();
            if ($data['code'] === '00000') {
                return $data['data'];
            }
        }

        return null;
    }

    /**
     * Generate HMAC-SHA512 signature.
     */
    public function generateSignature(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha512', $json, $this->secretKey);
    }

    /**
     * Verify callback signature.
     */
    public function verifyCallback(string $payloadJson, string $receivedSignature): bool
    {
        $calculatedSignature = hash_hmac('sha512', $payloadJson, $this->secretKey);

        return hash_equals($calculatedSignature, $receivedSignature);
    }
}
