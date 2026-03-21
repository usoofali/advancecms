<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAllocation;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Result;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentInvoice;
use Livewire\Component;

class StaffDashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $staff = Staff::where('email', $user->email)->with('institution')->first();
        $institutionId = $user->institution_id;
        $activeSession = AcademicSession::where('status', 'active')->first();

        // Lecturer Metrics
        $allocations = CourseAllocation::where('user_id', $user->id)
            ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
            ->get();

        $allocationsCount = $allocations->count();

        $totalRegisteredStudents = 0;
        $resultSubmissionCount = 0;

        if ($allocationsCount > 0) {
            $courseIds = $allocations->pluck('course_id')->unique();

            $totalRegisteredStudents = CourseRegistration::whereIn('course_id', $courseIds)
                ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
                ->count();

            // Submission progress: how many allocated courses have at least one result
            $resultSubmissionCount = Result::whereIn('course_id', $courseIds)
                ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
                ->distinct('course_id')
                ->count();
        }

        $submissionProgress = $allocationsCount > 0 ? round(($resultSubmissionCount / $allocationsCount) * 100, 1) : 0;

        // Accountant Metrics
        $pendingPaymentsCount = StudentInvoice::whereHas('payments', fn ($q) => $q->where('status', 'pending'))
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        $todayVerifiedCount = Payment::where('status', 'success')
            ->whereDate('updated_at', today())
            ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
            ->count();

        // Departmental Metrics (HOD or Allocated Departments)
        $hodDepartment = Department::where('hod_id', $staff?->id)->first();
        $departmentIds = collect();

        if ($hodDepartment) {
            $departmentIds->push($hodDepartment->id);
        }

        if ($allocationsCount > 0) {
            $allocationDeptIds = Course::whereIn('id', $allocations->pluck('course_id'))
                ->pluck('department_id')
                ->unique();
            $departmentIds = $departmentIds->merge($allocationDeptIds)->unique();
        }

        $totalDeptStudents = $departmentIds->isNotEmpty()
            ? Student::whereHas('program', fn ($q) => $q->whereIn('department_id', $departmentIds))->count()
            : 0;

        $totalDeptPrograms = $departmentIds->isNotEmpty()
            ? Program::whereIn('department_id', $departmentIds)->count()
            : 0;

        $departmentNames = $departmentIds->isNotEmpty()
            ? Department::whereIn('id', $departmentIds)->pluck('name')->implode(', ')
            : __('General');

        $stats = [
            'staff' => $staff,
            'is_hod' => (bool) $hodDepartment,
            'department_names' => $departmentNames,
            'activeSession' => $activeSession,
            'allocations_count' => $allocationsCount,
            'total_registered_students' => $totalRegisteredStudents,
            'submission_progress' => $submissionProgress,
            'pending_payments_count' => $pendingPaymentsCount,
            'today_verified_count' => $todayVerifiedCount,
            'total_dept_students' => $totalDeptStudents,
            'total_dept_programs' => $totalDeptPrograms,
        ];

        return view('livewire.pages.cms.dashboards.staff-dashboard', $stats);
    }
}
