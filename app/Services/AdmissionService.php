<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Notifications\AdmissionDecisionNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmissionService
{
    /**
     * Admit an applicant. Issues admission fees invoice and makes admission letter available.
     */
    public function admit(Applicant $applicant): void
    {
        DB::transaction(function () use ($applicant) {
            $applicant->update(['admission_status' => 'admitted']);

            // Find a prioritized "Admission Fee" invoice template
            $invoice = Invoice::where('institution_id', $applicant->institution_id)
                ->where('academic_session_id', $applicant->applicationForm->academic_session_id)
                ->where('status', 'published')
                ->where('category', Invoice::CATEGORY_ADMISSION)
                ->where(function ($q) use ($applicant) {
                    $q->where(function ($pq) use ($applicant) {
                        $pq->where('target_type', 'program')
                            ->where('program_id', $applicant->program_id);
                    })->orWhere(function ($dq) use ($applicant) {
                        $dq->where('target_type', 'dept')
                            ->where('department_id', $applicant->program->department_id);
                    })->orWhere(function ($lq) {
                        $lq->where('target_type', 'level')
                            ->where('level', 100);
                    });
                })
                ->orderByRaw("CASE 
                    WHEN target_type = 'program' THEN 1 
                    WHEN target_type = 'dept' THEN 2 
                    WHEN target_type = 'level' THEN 3 
                    ELSE 4 END")
                ->first();

            // Fallback for backward compatibility or if not configured properly
            if (! $invoice) {
                $invoice = Invoice::firstOrCreate(
                    [
                        'institution_id' => $applicant->institution_id,
                        'academic_session_id' => $applicant->applicationForm->academic_session_id,
                        'category' => Invoice::CATEGORY_ADMISSION,
                        'level' => 100,
                    ],
                    [
                        'title' => 'Admission & 100L Registration Fees',
                        'due_date' => now()->addMonth(),
                        'target_type' => 'student',
                        'status' => 'published',
                        'created_by' => auth()->id() ?? 1,
                    ]
                );

                // Ensure it has a default amount if newly created
                if ($invoice->items()->count() === 0) {
                    $invoice->items()->create([
                        'item_name' => 'Tuition & Registration Fees',
                        'amount' => 150000.00,
                    ]);
                }
            }

            StudentInvoice::updateOrCreate(
                [
                    'applicant_id' => $applicant->id,
                    'invoice_id' => $invoice->id,
                ],
                [
                    'institution_id' => $applicant->institution_id,
                    'total_amount' => $invoice->total_amount,
                    'balance' => $invoice->total_amount,
                    'status' => 'pending',
                ]
            );
        });

        // Notify the applicant
        $applicant->notify(new AdmissionDecisionNotification($applicant, 'admitted'));
    }

    /**
     * Reject an applicant.
     */
    public function reject(Applicant $applicant, ?string $reason = null): void
    {
        $applicant->update(['admission_status' => 'rejected']);

        // Notify the applicant
        $applicant->notify(new AdmissionDecisionNotification($applicant, 'rejected', $reason));
    }

    /**
     * Enroll an admitted applicant into the system as a fully registered student.
     *
     * @throws \Exception
     */
    public function enrollApplicant(Applicant $applicant): ?Student
    {
        if ($applicant->admission_status !== 'admitted') {
            throw new \Exception('Only admitted applicants can be enrolled.');
        }

        if ($applicant->enrolled_at) {
            // Already enrolled
            return Student::where('email', $applicant->email)->first();
        }

        try {
            return DB::transaction(function () use ($applicant) {
                // Parse full name to first and last name (simple split)
                $nameParts = explode(' ', trim($applicant->full_name), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                $credentials = $applicant->credential;

                // Create the student profile
                $student = Student::create([
                    'institution_id' => $applicant->institution_id,
                    'program_id' => $applicant->program_id,
                    // matric_number is automatically generated by the Student model's booted event
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $applicant->email,
                    'phone' => $applicant->phone,
                    'admission_year' => $applicant->applicationForm->academicSession->name ? (int) explode('/', $applicant->applicationForm->academicSession->name)[0] : date('Y'),
                    'entry_level' => $applicant->applicationForm->category === 'retrainee' ? 200 : 100,
                    'status' => 'active',
                    // Map credentials if available
                    'sitting_1_exam_type' => $credentials?->sitting_1_exam_type,
                    'sitting_1_exam_number' => $credentials?->sitting_1_exam_number,
                    'sitting_1_exam_year' => $credentials?->sitting_1_exam_year,
                    'sitting_2_exam_type' => $credentials?->sitting_2_exam_type,
                    'sitting_2_exam_number' => $credentials?->sitting_2_exam_number,
                    'sitting_2_exam_year' => $credentials?->sitting_2_exam_year,
                    'subject_english' => $credentials?->subject_english,
                    'subject_mathematics' => $credentials?->subject_mathematics,
                    'subject_biology' => $credentials?->subject_biology,
                    'subject_chemistry' => $credentials?->subject_chemistry,
                    'subject_physics' => $credentials?->subject_physics,
                ]);

                // Update applicant to mark as enrolled
                $applicant->update(['enrolled_at' => now()]);

                // Link existing applicant invoices to the new student record
                $applicant->studentInvoices()->update(['student_id' => $student->id]);

                Log::info("Applicant {$applicant->application_number} successfully enrolled as Student {$student->matric_number}");

                return $student;
            });
        } catch (\Exception $e) {
            Log::error("Failed to enroll applicant {$applicant->application_number}: ".$e->getMessage());
            throw $e;
        }
    }
}
