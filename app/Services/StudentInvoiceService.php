<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentInvoiceService
{
    /**
     * Check if a student is eligible for a specific invoice.
     */
    public function isEligible(Student $student, Invoice $invoice): bool
    {
        // 1. Basic status check
        if ($invoice->status !== 'published') {
            return false;
        }

        // 2. Tenancy check
        if ($invoice->institution_id !== $student->institution_id) {
            return false;
        }

        // 3. Department Check (Mandatory)
        // Student's program must belong to the invoice's department
        if ($student->program?->department_id !== $invoice->department_id) {
            return false;
        }

        // 4. Program Check (Optional)
        if ($invoice->program_id && $student->program_id !== $invoice->program_id) {
            return false;
        }

        // 5. Level Check (Optional)
        if ($invoice->level) {
            $currentLevel = $student->currentLevel($invoice->academicSession);
            if ((string) $currentLevel !== $invoice->level) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all invoices a student is eligible for but hasn't materialized.
     */
    public function getAvailableInvoices(Student $student): Collection
    {
        $publishedInvoices = Invoice::where('status', 'published')
            ->where('institution_id', $student->institution_id)
            ->with(['academicSession', 'items'])
            ->get();

        return $publishedInvoices->filter(function ($invoice) use ($student) {
            // Check eligibility
            if (! $this->isEligible($student, $invoice)) {
                return false;
            }

            // Check if already materialized
            return ! StudentInvoice::where('student_id', $student->id)
                ->where('invoice_id', $invoice->id)
                ->exists();
        });
    }

    /**
     * Materialize an invoice for a student.
     */
    public function materializeInvoice(Student $student, Invoice $invoice): ?StudentInvoice
    {
        if (! $this->isEligible($student, $invoice)) {
            return null;
        }

        return DB::transaction(function () use ($student, $invoice) {
            // Double check existence in transaction
            $existing = StudentInvoice::where('student_id', $student->id)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $totalAmount = $invoice->items()->sum('amount');

            return StudentInvoice::create([
                'institution_id' => $student->institution_id,
                'student_id' => $student->id,
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'balance' => $totalAmount,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Calculate potential revenue for an invoice.
     */
    public function getInvoiceStats(Invoice $invoice): array
    {
        $generated = $invoice->studentInvoices()->count();
        $totalPaid = $invoice->studentInvoices()->sum('amount_paid');
        $totalOwed = $invoice->studentInvoices()->sum('balance');

        // This is a rough estimation - in a real app, you'd query students matching target_type
        // who don't have a record yet.
        return [
            'generated_count' => $generated,
            'total_paid' => $totalPaid,
            'total_owed' => $totalOwed,
        ];
    }

    /**
     * Force generate invoices for eligible students based on a specific level (or all levels).
     */
    public function forceGenerate(Invoice $invoice, string $levelTarget = 'all'): int
    {
        $session = $invoice->academicSession;
        $sessionYear = (int) explode('/', $session->name)[0];

        // 1. Build a query of students who belong to the invoice's department
        $query = Student::where('institution_id', $invoice->institution_id)
            ->whereHas('program', function ($q) use ($invoice) {
                $q->where('department_id', $invoice->department_id);
                if ($invoice->program_id) {
                    $q->where('id', $invoice->program_id);
                }
            });

        // 2. Filter by Level Target (All or specific level)
        // If the invoice template itself mandates a level, that overrides the "All" choice,
        // though typically `isEligible` would catch that later. We'll enforce the UI choice here.
        if ($levelTarget !== 'all') {
            $level = (int) $levelTarget;
            // admission_year = sessionYear - ((Level - entry_level) / 100)
            $query->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
        } elseif ($invoice->level) {
            // Unlikely condition in loop, but if the master invoice demands a level
            $level = (int) $invoice->level;
            $query->whereRaw('admission_year = ? - ((? - entry_level) / 100)', [$sessionYear, $level]);
        }

        // 3. Exclude students who already have this invoice generated
        $query->whereNotIn('id', function ($q) use ($invoice) {
            $q->select('student_id')
                ->from('student_invoices')
                ->where('invoice_id', $invoice->id);
        });

        // Loop and materialize
        $count = 0;
        foreach ($query->cursor() as $student) {
            if ($this->materializeInvoice($student, $invoice)) {
                $count++;
            }
        }

        return $count;
    }
}
