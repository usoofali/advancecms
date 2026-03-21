<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;

class PaymentAccessService
{
    /**
     * Determine if a student can access their exam card for a specific session/semester.
     */
    public function canAccessExamCard(Student $student, AcademicSession $session, Semester $semester): bool
    {
        return $this->canAccessByCategory(
            $student,
            $session,
            $semester,
            Invoice::CATEGORY_EXAM
        );
    }

    /**
     * Determine if a student can access their results for a specific session/semester.
     */
    public function canAccessResults(Student $student, AcademicSession $session, Semester $semester): bool
    {
        return $this->canAccessByCategory(
            $student,
            $session,
            $semester,
            Invoice::CATEGORY_RESULT
        );
    }

    /**
     * Generic logic to check access based on invoice category.
     * Access is ALWAYS granted if no invoice template exists for this context.
     * If a template exists, the student must have a corresponding "paid" student invoice.
     */
    protected function canAccessByCategory(Student $student, AcademicSession $session, Semester $semester, string $category): bool
    {
        // 1. Check if there are ANY invoice templates for this category/session/semester
        // that apply to this student (matching program/level etc.)
        $applicableInvoices = Invoice::where('category', $category)
            ->where('academic_session_id', $session->id)
            ->where(function ($q) use ($semester) {
                $q->whereNull('semester_id')->orWhere('semester_id', $semester->id);
            })
            ->where('status', 'published')
            ->where(function ($q) use ($student, $session) {
                // Check targeting logic similar to StudentInvoiceService
                $q->where('target_type', 'student')
                    ->orWhere(function ($sq) use ($student) {
                        $sq->where('target_type', 'program')
                            ->where('program_id', $student->program_id);
                    })
                    ->orWhere(function ($sq) use ($student, $session) {
                        $sq->where('target_type', 'level')
                            ->where('level', $student->currentLevel($session));
                    })
                    ->orWhere(function ($sq) use ($student) {
                        $sq->where('target_type', 'dept')
                            ->where('department_id', $student->program?->department_id);
                    });
            })
            ->get();

        // If no templates exist, access is granted (opt-in behavior)
        if ($applicableInvoices->isEmpty()) {
            return true;
        }

        // 2. If templates exist, student MUST have a PAID student invoice for EACH applicable template
        foreach ($applicableInvoices as $invoice) {
            $isPaid = StudentInvoice::where('student_id', $student->id)
                ->where('invoice_id', $invoice->id)
                ->where('status', 'paid')
                ->exists();

            if (! $isPaid) {
                return false;
            }
        }

        return true;
    }
}
