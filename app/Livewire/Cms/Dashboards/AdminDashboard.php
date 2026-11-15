<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\CbtResultStaging;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Institution;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\PayrollRun;
use App\Models\Program;
use App\Models\Result;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentPlacement;
use App\Models\StudentProject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminDashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $instId = $isSuperAdmin ? null : $user->institution_id;

        $activeSession = AcademicSession::where('status', 'active')->first();

        $totalInvoiced = (float) StudentInvoice::when($instId, fn ($q) => $q->where('institution_id', $instId))->sum('total_amount');
        $totalCollected = (float) StudentInvoice::when($instId, fn ($q) => $q->where('institution_id', $instId))->sum('amount_paid');
        $collectionEfficiency = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;

        $studentsQuery = Student::when($instId, fn ($q) => $q->where('institution_id', $instId));
        $studentsCount = (clone $studentsQuery)->count();
        $maleCount = (clone $studentsQuery)->where('gender', 'male')->count();
        $femaleCount = (clone $studentsQuery)->where('gender', 'female')->count();
        $newEnrollments = (clone $studentsQuery)->where('created_at', '>=', now()->subDays(30))->count();

        $resultsQuery = Result::when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
            ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id));

        $averageGpa = round((float) ((clone $resultsQuery)->avg('grade_point') ?? 0), 2);
        $failedResultsCount = (clone $resultsQuery)->where('grade', 'F')->count();

        $registeredStudentIdsCount = 0;
        if ($activeSession) {
            $registeredStudentIdsCount = CourseRegistration::where('academic_session_id', $activeSession->id)
                ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
                ->distinct('student_id')
                ->count('student_id');
        }
        $registrationVelocity = $studentsCount > 0 ? round(($registeredStudentIdsCount / $studentsCount) * 100, 1) : 0;

        $hasInventoryAddon = $isSuperAdmin || ($user->institution?->hasAddon('inventory') ?? false);
        $inventoryLocationsCount = 0;
        $inventoryLowStockCount = 0;

        if ($hasInventoryAddon) {
            $inventoryLocationsCount = InventoryLocation::forInstitution($instId)
                ->where('status', 'active')
                ->count();

            $inventoryLowStockCount = InventoryItem::when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->where('status', 'active')
                ->where('min_stock_threshold', '>', 0)
                ->withSum('stocks', 'quantity')
                ->get()
                ->filter(fn (InventoryItem $item) => $item->isBelowThreshold((int) ($item->stocks_sum_quantity ?? 0)))
                ->count();
        }

        $hasPayrollAddon = $isSuperAdmin || ($user->institution?->hasAddon('payroll') ?? false);
        $latestPayrollRun = null;
        $pendingPayrollRunsCount = 0;

        if ($hasPayrollAddon) {
            $latestPayrollRun = PayrollRun::when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->latest('year')
                ->latest('month')
                ->latest('id')
                ->first();

            $pendingPayrollRunsCount = PayrollRun::when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->where('status', 'under_review')
                ->count();
        }

        $stats = [
            'is_super_admin' => $isSuperAdmin,
            'institution' => $user->institution,
            'institutions_count' => $isSuperAdmin ? Institution::count() : 1,
            'departments_count' => Department::when($instId, fn ($q) => $q->where('institution_id', $instId))->count(),
            'programs_count' => Program::when($instId, fn ($q) => $q->where('institution_id', $instId))->count(),
            'students_count' => $studentsCount,
            'staff_count' => Staff::when($instId, fn ($q) => $q->where('institution_id', $instId))->count(),
            'courses_count' => Course::when($instId, fn ($q) => $q->where('institution_id', $instId))->count(),
            'unpaid_invoices_count' => StudentInvoice::when($instId, fn ($q) => $q->where('institution_id', $instId))->where('status', '!=', 'paid')->count(),
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'collection_efficiency' => $collectionEfficiency,
            'outstanding_debt' => max(0, $totalInvoiced - $totalCollected),
            'activeSession' => $activeSession,
            'average_gpa' => $averageGpa,
            'failed_results_count' => $failedResultsCount,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'new_enrollments' => $newEnrollments,
            'registration_velocity' => $registrationVelocity,
            'pending_applicants_count' => Applicant::when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->where('admission_status', 'pending')
                ->count(),
            'pending_cbt_reviews_count' => CbtResultStaging::where('status', 'pending')
                ->when($instId, fn ($q) => $q->whereHas('exam', fn ($eq) => $eq->where('institution_id', $instId)))
                ->count(),
            'active_placements_count' => StudentPlacement::where('status', 'active')
                ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
                ->count(),
            'active_projects_count' => StudentProject::when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->whereNotIn('overall_status', ['completed', 'archived'])
                ->count(),
            'hasInventoryAddon' => $hasInventoryAddon,
            'inventory_locations_count' => $inventoryLocationsCount,
            'inventory_low_stock_count' => $inventoryLowStockCount,
            'hasPayrollAddon' => $hasPayrollAddon,
            'latest_payroll_run' => $latestPayrollRun,
            'pending_payroll_runs_count' => $pendingPayrollRunsCount,
            'recentActivities' => ActivityLog::with('user')
                ->forInstitution($instId)
                ->latest()
                ->take(5)
                ->get(),
        ];

        return view('livewire.pages.cms.dashboards.admin-dashboard', $stats);
    }
}
