<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\CbtResultStaging;
use App\Models\CourseAllocation;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Payment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Program;
use App\Models\Result;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentProject;
use App\Models\Timetable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StaffDashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $staff = $user->staff;
        $instId = $user->institution_id;
        $activeSession = AcademicSession::where('status', 'active')->first();

        // Check if staff is HOD of any department
        $hodDepts = $staff ? Department::where('hod_id', $staff->id)->get() : collect();
        $isHod = $hodDepts->isNotEmpty() || $user->hasRole('HOD');

        // Lecturer stats
        $allocations = CourseAllocation::where('user_id', $user->id)
            ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
            ->get();

        $allocationsCount = $allocations->count();
        $courseIds = $allocations->pluck('course_id')->unique();

        $totalRegisteredStudents = 0;
        $submittedResultsCount = 0;

        if ($activeSession && $courseIds->isNotEmpty()) {
            $totalRegisteredStudents = CourseRegistration::whereIn('course_id', $courseIds)
                ->where('academic_session_id', $activeSession->id)
                ->count();

            $submittedResultsCount = Result::whereIn('course_id', $courseIds)
                ->where('academic_session_id', $activeSession->id)
                ->count();
        }

        $submissionProgress = $totalRegisteredStudents > 0
            ? min(100, round(($submittedResultsCount / $totalRegisteredStudents) * 100, 1))
            : 0;

        // Accountant stats
        $pendingPaymentsCount = 0;
        $todayVerifiedCount = 0;
        if ($user->can('invoices.view') || $user->can('payments.verify')) {
            $pendingPaymentsCount = StudentInvoice::where('institution_id', $instId)
                ->where('status', '!=', 'paid')
                ->count();

            $todayVerifiedCount = Payment::where('institution_id', $instId)
                ->where('status', 'verified')
                ->whereDate('created_at', today())
                ->count();
        }

        // Dept stats if HOD
        $deptIds = $hodDepts->pluck('id');
        if ($deptIds->isEmpty() && $staff?->department_id) {
            $deptIds = collect([$staff->department_id]);
        }

        $totalDeptStudents = Student::whereIn('department_id', $deptIds)->count();
        $totalDeptPrograms = Program::whereIn('department_id', $deptIds)->count();

        $showTeachingKpis = $user->hasRole('Lecturer') || $user->can('courses.view_assigned') || $allocationsCount > 0;
        $showAcademicDeptKpis = $isHod || $user->can('students.view_dept') || $user->can('view_dept_programs');

        $todayTimetableCount = 0;
        if ($showTeachingKpis) {
            $todayTimetableCount = Timetable::forLecturer($user)
                ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
                ->where('day_of_week', now()->format('l'))
                ->count();
        }

        // Inventory stats
        $hasInventoryAddon = $user->can('inventory.view');
        $inventoryLocationsCount = 0;
        $inventoryLowStockCount = 0;
        if ($hasInventoryAddon) {
            $inventoryLocationsCount = InventoryLocation::accessibleBy($user)
                ->where('status', 'active')
                ->count();

            $inventoryLowStockCount = InventoryItem::where('institution_id', $instId)
                ->where('status', 'active')
                ->where('min_stock_threshold', '>', 0)
                ->withSum('stocks', 'quantity')
                ->get()
                ->filter(fn (InventoryItem $item) => $item->isBelowThreshold((int) ($item->stocks_sum_quantity ?? 0)))
                ->count();
        }

        // Payroll stats
        $hasPayrollAddon = $user->can('payroll.view');
        $latestPayrollRun = null;
        if ($hasPayrollAddon) {
            $latestPayrollRun = PayrollRun::where('institution_id', $instId)
                ->latest('year')
                ->latest('month')
                ->latest('id')
                ->first();
        }

        $latestPayslip = null;
        $canViewOwnPayslip = $staff && $user->can('payroll.view_own_payslip');
        if ($canViewOwnPayslip) {
            $latestPayslip = PayrollItem::with('run')
                ->where('staff_id', $staff->id)
                ->whereHas('run', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
                ->latest('id')
                ->first();
        }

        $pendingApplicantsCount = $user->can('applications.view')
            ? Applicant::where('institution_id', $instId)->where('admission_status', 'pending')->count()
            : 0;

        $hasExamAddon = $user->can('cbt_results.view');
        $pendingCbtReviewsCount = $hasExamAddon
            ? CbtResultStaging::where('status', 'pending')
                ->whereHas('exam', fn ($q) => $q->where('institution_id', $instId))
                ->count()
            : 0;

        $supervisedProjectsCount = ($staff && $user->can('projects.supervise'))
            ? StudentProject::where('supervisor_id', $staff->id)
                ->whereNotIn('overall_status', ['completed', 'archived'])
                ->count()
            : 0;

        $stats = [
            'user' => $user,
            'staff' => $staff,
            'is_hod' => $isHod,
            'department_names' => $hodDepts->pluck('name')->implode(', ') ?: ($staff?->department?->name ?? __('Unassigned')),
            'activeSession' => $activeSession,
            'show_teaching_kpis' => $showTeachingKpis,
            'show_academic_dept_kpis' => $showAcademicDeptKpis,
            'allocations_count' => $allocationsCount,
            'total_registered_students' => $totalRegisteredStudents,
            'submission_progress' => $submissionProgress,
            'today_timetable_count' => $todayTimetableCount,
            'pending_payments_count' => $pendingPaymentsCount,
            'today_verified_count' => $todayVerifiedCount,
            'total_dept_students' => $totalDeptStudents,
            'total_dept_programs' => $totalDeptPrograms,
            'hasInventoryAddon' => $hasInventoryAddon,
            'inventory_locations_count' => $inventoryLocationsCount,
            'inventory_low_stock_count' => $inventoryLowStockCount,
            'hasPayrollAddon' => $hasPayrollAddon || $canViewOwnPayslip,
            'latest_payroll_run' => $latestPayrollRun,
            'latest_payslip' => $latestPayslip,
            'pending_applicants_count' => $pendingApplicantsCount,
            'hasExamAddon' => $hasExamAddon,
            'pending_cbt_reviews_count' => $pendingCbtReviewsCount,
            'supervised_projects_count' => $supervisedProjectsCount,
        ];

        return view('livewire.pages.cms.dashboards.staff-dashboard', $stats);
    }
}
