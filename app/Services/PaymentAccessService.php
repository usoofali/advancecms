<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Support\Collection;

class PaymentAccessService
{
    /**
     * Get missing required invoices for accessing exam card.
     * Returns an empty collection if access is granted.
     */
    public function getMissingInvoicesForExamCard(Student $student, AcademicSession $session, Semester $semester): Collection
    {
        return $this->getMissingRequiredInvoices(
            $student,
            $session,
            $semester,
            'is_required_for_exams'
        );
    }

    /**
     * Get missing required invoices for accessing results.
     * Returns an empty collection if access is granted.
     */
    public function getMissingInvoicesForResults(Student $student, AcademicSession $session, Semester $semester): Collection
    {
        return $this->getMissingRequiredInvoices(
            $student,
            $session,
            $semester,
            'is_required_for_results'
        );
    }

    /**
     * Generic logic to retrieve missing required invoices based on a boolean flag.
     */
    protected function getMissingRequiredInvoices(Student $student, AcademicSession $session, Semester $semester, string $flagColumn): Collection
    {
        // 1. Check if there are ANY invoice templates flagged for this context/session/semester
        // that apply to this student (matching program/level etc.)
        $applicableInvoices = Invoice::where($flagColumn, true)
            ->where('academic_session_id', $session->id)
            ->where(function ($q) use ($semester) {
                $q->whereNull('semester_id')->orWhere('semester_id', $semester->id);
            })
            ->where('status', 'published')
            ->where('department_id', $student->program?->department_id)
            ->where(function ($q) use ($student) {
                $q->whereNull('program_id')
                    ->orWhere('program_id', $student->program_id);
            })
            ->where(function ($q) use ($student, $session) {
                $q->whereNull('level')
                    ->orWhere('level', (string) $student->currentLevel($session));
            })
            ->get();

        // If no templates exist, none are missing (access is granted)
        if ($applicableInvoices->isEmpty()) {
            return collect();
        }

        $missingInvoices = collect();

        // 2. If templates exist, student MUST have a PAID student invoice for EACH applicable template
        foreach ($applicableInvoices as $invoice) {
            $isPaid = StudentInvoice::where('student_id', $student->id)
                ->where('invoice_id', $invoice->id)
                ->where('status', 'paid')
                ->exists();

            if (! $isPaid) {
                $missingInvoices->push($invoice);
            }
        }

        return $missingInvoices;
    }
}
