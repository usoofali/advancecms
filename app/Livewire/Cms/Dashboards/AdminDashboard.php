<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Result;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentInvoice;
use Livewire\Component;

class AdminDashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $institutionId = $user->institution_id;

        $invoicesQuery = StudentInvoice::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId));
        $totalInvoiced = $invoicesQuery->sum('total_amount') ?: 0;
        $totalCollected = $invoicesQuery->sum('amount_paid') ?: 0;
        $collectionEfficiency = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;
        $outstandingDebt = $invoicesQuery->sum('balance') ?: 0;

        $resultsQuery = Result::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId));
        $totalResults = $resultsQuery->count();
        $averageGpa = $resultsQuery->avg('grade_point') ?: 0;
        $failedResultsCount = $resultsQuery->where('remark', 'fail')->count();

        $studentsQuery = Student::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId));
        $maleCount = (clone $studentsQuery)->where('gender', 'Male')->count();
        $femaleCount = (clone $studentsQuery)->where('gender', 'Female')->count();
        $newEnrollments = (clone $studentsQuery)->where('admission_year', date('Y'))->count();

        $activeSession = AcademicSession::where('status', 'active')->first();
        $registrationVelocity = CourseRegistration::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId))
            ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
            ->count();

        $stats = [
            'is_super_admin' => $isSuperAdmin,
            'institutions_count' => $isSuperAdmin ? Institution::count() : 0,
            'departments_count' => Department::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId))->count(),
            'programs_count' => Program::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId))->count(),
            'students_count' => (clone $studentsQuery)->count(),
            'staff_count' => Staff::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId))->count(),
            'courses_count' => Course::when(! $isSuperAdmin, fn ($q) => $q->where('institution_id', $institutionId))->count(),
            'unpaid_invoices_count' => (clone $invoicesQuery)->whereIn('status', ['unpaid', 'partial'])->count(),

            // Advanced Financials
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'collection_efficiency' => $collectionEfficiency,
            'outstanding_debt' => $outstandingDebt,
            'activeSession' => $activeSession,

            // Academic Analytics
            'average_gpa' => round($averageGpa, 2),
            'failed_results_count' => $failedResultsCount,

            // Demographics & Trends
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'new_enrollments' => $newEnrollments,
            'registration_velocity' => $registrationVelocity,
        ];

        return view('livewire.pages.cms.dashboards.admin-dashboard', $stats);
    }
}
