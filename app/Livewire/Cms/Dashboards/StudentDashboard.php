<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Services\GradingService;
use Livewire\Component;

class StudentDashboard extends Component
{
    public function render(GradingService $gradingService)
    {
        $user = auth()->user();
        $student = Student::where('email', $user->email)->first();

        $activeSession = AcademicSession::where('status', 'active')->first();

        $results = $student ? $student->results()->get() : collect();
        $totalResults = $results->count();
        $passedCount = $results->where('remark', 'pass')->count();
        $failedCount = $results->where('remark', 'fail')->count();
        $passRate = $totalResults > 0 ? round(($passedCount / $totalResults) * 100, 1) : 0;

        $invoices = $student ? StudentInvoice::where('student_id', $student->id)->get() : collect();

        $stats = [
            'student' => $student,
            'cgpa' => $student ? $gradingService->computeCgpa($student) : 0,
            'total_units' => $student ? $student->results()->with('course')->get()->sum(fn ($r) => $r->course->credit_unit ?? 0) : 0,
            'pending_balance' => $invoices->whereIn('status', ['unpaid', 'partial'])->sum('balance'),
            'registration_status' => $student ? ($student->courseRegistrations()->whereHas('academicSession', fn ($q) => $q->where('status', 'active'))->exists() ? 'Registered' : 'Not Registered') : 'N/A',
            'registered_courses_count' => $student ? $student->courseRegistrations()->whereHas('academicSession', fn ($q) => $q->where('status', 'active'))->count() : 0,
            'current_level' => ($student && $activeSession) ? $student->currentLevel($activeSession) : '—',

            // Financial Status Tally
            'paid_invoices_count' => $invoices->where('status', 'paid')->count(),
            'partial_invoices_count' => $invoices->where('status', 'partial')->count(),
            'unpaid_invoices_count' => $invoices->where('status', 'unpaid')->count(),

            // Academic Performance
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
            'pass_rate' => $passRate,

            // Profile Completion
            'profile_completion' => $student?->completion_percentage ?? 0,

            // Attendance
            'overall_attendance' => $student?->getAttendancePercentage() ?? 0,
        ];

        return view('livewire.pages.cms.dashboards.student-dashboard', $stats);
    }
}
