<?php

namespace App\Http\Controllers\Gateway\Hub;

use App\Http\Controllers\Controller;
use App\Models\Hub\Tenant;
use App\Models\Hub\TenantUserCache;
use App\Services\Gateway\WhatsAppClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppClientService $whatsAppClient
    ) {}

    /**
     * Handle Meta WhatsApp webhook verification challenge (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('gateway.hub.whatsapp.verify_token');

        if ($mode === 'subscribe' && ! empty($expectedToken) && $token === $expectedToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle incoming WhatsApp webhook messages (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Extract message entry from Meta Graph API webhook structure
        $entry = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (! $entry || empty($entry['messages'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $messageData = $entry['messages'][0];
        $fromPhone = preg_replace('/[^0-9]/', '', (string) ($messageData['from'] ?? ''));
        $messageBody = trim((string) ($messageData['text']['body'] ?? ''));

        if (empty($fromPhone) || empty($messageBody)) {
            return response()->json(['status' => 'empty_payload'], 200);
        }

        // Search tenant_user_cache for existing phone mapping
        $userCache = TenantUserCache::where('phone_number', $fromPhone)
            ->with('tenant')
            ->first();

        if (! $userCache || ! $userCache->tenant || $userCache->tenant->status !== 'active') {
            // Automatically search across all active Spoke campus databases by phone number
            $userCache = $this->attemptAutoPhoneLookup($fromPhone);
        }

        if ($userCache && $userCache->tenant && $userCache->tenant->status === 'active') {
            $this->forwardQueryToSpoke($userCache, $messageBody, $fromPhone);
        } else {
            $this->handleOnboarding($fromPhone, $messageBody);
        }

        return response()->json(['status' => 'processed'], 200);
    }

    /**
     * Search all active Spoke campus databases to automatically identify a student or staff by phone number.
     */
    protected function attemptAutoPhoneLookup(string $fromPhone): ?TenantUserCache
    {
        $activeTenants = Tenant::where('status', 'active')->get();
        if ($activeTenants->isEmpty()) {
            return null;
        }

        $phoneVariations = [$fromPhone];
        if (str_starts_with($fromPhone, '234')) {
            $phoneVariations[] = '0'.substr($fromPhone, 3);
            $phoneVariations[] = '+'.$fromPhone;
        } elseif (str_starts_with($fromPhone, '0')) {
            $phoneVariations[] = '234'.substr($fromPhone, 1);
            $phoneVariations[] = '+234'.substr($fromPhone, 1);
        }
        $lastDigits = strlen($fromPhone) >= 8 ? substr($fromPhone, -8) : $fromPhone;

        foreach ($activeTenants as $tenant) {
            try {
                $conn = $tenant->getDatabaseConnectionName();

                // Search students table
                $student = DB::connection($conn)->table('students')
                    ->where(function ($q) use ($phoneVariations, $lastDigits) {
                        $q->whereIn('phone', $phoneVariations)
                            ->orWhere('phone', 'like', '%'.$lastDigits);
                    })
                    ->first();

                if ($student) {
                    $fullName = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));

                    return TenantUserCache::updateOrCreate(
                        ['phone_number' => $fromPhone],
                        [
                            'tenant_id' => $tenant->id,
                            'external_user_id' => $student->matric_number,
                            'role' => 'student',
                            'institution_id' => $student->institution_id ?? null,
                            'metadata' => [
                                'name' => $fullName ?: 'Student',
                                'student_id' => $student->id,
                                'status' => $student->status ?? 'active',
                                'campus' => $tenant->name,
                                'permissions' => ['check_balance', 'check_status', 'check_invoices'],
                            ],
                            'last_active_at' => now(),
                        ]
                    );
                }

                // Search staff table
                $hasPhoneNumberCol = Schema::connection($conn)->hasColumn('staff', 'phone_number');
                $staff = DB::connection($conn)->table('staff')
                    ->where(function ($q) use ($phoneVariations, $lastDigits, $hasPhoneNumberCol) {
                        $q->whereIn('phone', $phoneVariations)
                            ->orWhere('phone', 'like', '%'.$lastDigits);
                        if ($hasPhoneNumberCol) {
                            $q->orWhereIn('phone_number', $phoneVariations)
                                ->orWhere('phone_number', 'like', '%'.$lastDigits);
                        }
                    })
                    ->first();

                if ($staff) {
                    $staffIdentifier = $staff->staff_number ?? $staff->email ?? ('STAFF-'.$staff->id);
                    $staffName = $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? ''));

                    return TenantUserCache::updateOrCreate(
                        ['phone_number' => $fromPhone],
                        [
                            'tenant_id' => $tenant->id,
                            'external_user_id' => $staffIdentifier,
                            'role' => 'staff',
                            'institution_id' => $staff->institution_id ?? null,
                            'metadata' => [
                                'name' => $staffName ?: 'Staff Member',
                                'staff_id' => $staff->id,
                                'staff_role' => $staff->role ?? 'lecturer',
                                'department_id' => $staff->department_id ?? null,
                                'campus' => $tenant->name,
                                'permissions' => ['staff_schedule', 'department_overview', 'student_lookup'],
                            ],
                            'last_active_at' => now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning(sprintf('Phone auto-lookup error on tenant DB [%s]: %s', $tenant->code, $e->getMessage()));
            }
        }

        return null;
    }

    /**
     * Forward query to authenticated Spoke node via Direct Database connection and send response back to user.
     */
    protected function forwardQueryToSpoke(TenantUserCache $userCache, string $messageBody, string $fromPhone): void
    {
        $tenant = $userCache->tenant;

        try {
            $conn = $tenant->getDatabaseConnectionName();
            $instId = $userCache->institution_id ?? $tenant->institution_id;
            $externalUserId = $userCache->external_user_id;
            $role = $userCache->role;
            $metadata = $userCache->metadata ?? [];
            $lowerBody = strtolower($messageBody);

            if ($role === 'staff') {
                $staffQuery = DB::connection($conn)->table('staff')->where(function ($q) use ($externalUserId) {
                    $q->where('staff_number', $externalUserId)
                        ->orWhere('email', $externalUserId);
                });
                if ($instId) {
                    $staffQuery->where('institution_id', $instId);
                }
                $staff = $staffQuery->first();
                $name = $staff->name ?? ($metadata['name'] ?? 'Staff Member');
                $replyText = "Hello {$name}! Welcome to the {$tenant->name} Staff WhatsApp Portal.";
            } else {
                $studentQuery = DB::connection($conn)->table('students')->where('matric_number', $externalUserId);
                if ($instId) {
                    $studentQuery->where('institution_id', $instId);
                }
                $student = $studentQuery->first();
                $studentName = $student ? trim("{$student->first_name} {$student->last_name}") : ($metadata['name'] ?? 'Student');

                if (str_contains($lowerBody, 'balance') || str_contains($lowerBody, 'fee') || str_contains($lowerBody, 'invoice')) {
                    $unpaidSum = 0.0;
                    if ($student) {
                        $invoiceQuery = DB::connection($conn)->table('student_invoices')
                            ->where('student_id', $student->id)
                            ->whereIn('status', ['unpaid', 'pending', 'partial']);
                        if ($instId) {
                            $invoiceQuery->where('institution_id', $instId);
                        }
                        $unpaidSum = (float) $invoiceQuery->sum('amount');
                    }
                    $replyText = sprintf('Dear %s, your outstanding balance at %s is N%s.', $studentName, $tenant->name, number_format($unpaidSum, 2));
                } elseif (str_contains($lowerBody, 'status') || str_contains($lowerBody, 'profile')) {
                    $status = $student->status ?? ($metadata['status'] ?? 'Active');
                    $replyText = sprintf('Dear %s, your registration status at %s is: %s.', $studentName, $tenant->name, ucfirst($status));
                } else {
                    $replyText = sprintf('Hello %s (%s)! Welcome to %s. Reply "check balance" or "check status" for more information.', $studentName, $externalUserId, $tenant->name);
                }
            }

            $userCache->update(['last_active_at' => now()]);
            $this->whatsAppClient->sendMessage($fromPhone, $replyText);

            return;
        } catch (\Throwable $e) {
            Log::error('Error processing Direct DB WhatsApp query', ['error' => $e->getMessage()]);
            $this->whatsAppClient->sendMessage($fromPhone, 'Sorry, we could not connect to your campus database at this moment.');

            return;
        }
    }

    /**
     * Handle onboarding flow for unknown phone numbers using Direct Database lookup.
     */
    protected function handleOnboarding(string $fromPhone, string $messageBody): void
    {
        $parts = preg_split('/\s+/', trim($messageBody));

        if (count($parts) >= 2) {
            $collegeCode = strtoupper($parts[0]);
            $studentId = strtoupper(implode(' ', array_slice($parts, 1)));

            $tenant = Tenant::where(function ($q) use ($collegeCode) {
                $q->where('code', $collegeCode);
                if (is_numeric($collegeCode)) {
                    $q->orWhere('id', (int) $collegeCode);
                }
            })
                ->where('status', 'active')
                ->first();

            if ($tenant) {
                try {
                    $conn = $tenant->getDatabaseConnectionName();

                    $student = DB::connection($conn)->table('students')->where('matric_number', $studentId)->first();

                    if ($student) {
                        TenantUserCache::updateOrCreate(
                            ['phone_number' => $fromPhone],
                            [
                                'tenant_id' => $tenant->id,
                                'external_user_id' => $studentId,
                                'role' => 'student',
                                'institution_id' => $student->institution_id ?? null,
                                'metadata' => [
                                    'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                                    'student_id' => $student->id,
                                    'campus' => $tenant->name,
                                ],
                                'last_active_at' => now(),
                            ]
                        );

                        $reply = sprintf('Welcome! Your phone number is now linked to %s (%s). You can now text queries directly.', $tenant->name, $studentId);
                        $this->whatsAppClient->sendMessage($fromPhone, $reply);

                        return;
                    }

                    $staff = DB::connection($conn)->table('staff')->where('staff_number', $studentId)->first();

                    if ($staff) {
                        TenantUserCache::updateOrCreate(
                            ['phone_number' => $fromPhone],
                            [
                                'tenant_id' => $tenant->id,
                                'external_user_id' => $studentId,
                                'role' => 'staff',
                                'institution_id' => $staff->institution_id ?? null,
                                'metadata' => [
                                    'name' => $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')),
                                    'staff_id' => $staff->id,
                                    'campus' => $tenant->name,
                                ],
                                'last_active_at' => now(),
                            ]
                        );

                        $reply = sprintf('Welcome! Your phone number is now linked to %s (%s). You can now text queries directly.', $tenant->name, $studentId);
                        $this->whatsAppClient->sendMessage($fromPhone, $reply);

                        return;
                    }
                } catch (\Throwable $e) {
                    Log::error('Error verifying user on Direct DB Spoke', ['error' => $e->getMessage()]);
                }

                $this->whatsAppClient->sendMessage($fromPhone, sprintf('Invalid admission or ID number (%s) for %s.', $studentId, $tenant->name));

                return;
            }
        }

        $prompt = "Welcome to AdvanceCMS! Reply with your Campus Code and Student/Staff ID to activate your account:\n{CAMPUS_CODE} {ID_NUMBER}\nExample: MAIN STU/2026/0012";
        $this->whatsAppClient->sendMessage($fromPhone, $prompt);
    }
}
