<?php

namespace App\Livewire\Cms\Dashboards;

use App\Models\AcademicSession;
use App\Models\IdCardRequest;
use App\Models\Student;
use App\Models\StudentCoin;
use App\Models\StudentInvoice;
use App\Models\StudentPlacement;
use App\Models\StudentProject;
use App\Models\Timetable;
use App\Services\GradingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StudentDashboard extends Component
{
    public function render(GradingService $gradingService): View
    {
        $user = auth()->user();
        $student = $user->student ?? Student::where('email', $user->email)->first();

        $activeSession = AcademicSession::where('status', 'active')->first();

        $isResultsLocked = (bool) $student?->program?->results_locked;
        $results = ($student && ! $isResultsLocked) ? $student->results()->get() : collect();
        $totalResults = $results->count();
        $passedCount = $results->where('remark', 'pass')->count();
        $failedCount = $results->where('remark', 'fail')->count();
        $passRate = $totalResults > 0 ? round(($passedCount / $totalResults) * 100, 1) : 0;

        $invoices = $student ? StudentInvoice::where('student_id', $student->id)->get() : collect();

        $hasCaAddon = $user->can('registrations.view_personal');
        $coinsBalance = ($hasCaAddon && $student)
            ? (int) (StudentCoin::where('student_id', $student->id)->value('total_coins') ?? 0)
            : 0;

        $todayTimetableCount = $student
            ? Timetable::forStudent($user)
                ->when($activeSession, fn ($q) => $q->where('academic_session_id', $activeSession->id))
                ->where('day_of_week', now()->format('l'))
                ->count()
            : 0;

        $stats = [
            'user' => $user,
            'student' => $student,
            'activeSession' => $activeSession,
            'is_results_locked' => $isResultsLocked,
            'cgpa' => ($student && ! $isResultsLocked) ? $gradingService->computeCgpa($student) : 0,
            'total_units' => $student ? $student->results()->with('course')->get()->sum(fn ($r) => $r->course->credit_unit ?? 0) : 0,
            'pending_balance' => $invoices->whereIn('status', ['unpaid', 'partial'])->sum('balance'),
            'registration_status' => $student ? ($student->courseRegistrations()->whereHas('academicSession', fn ($q) => $q->where('status', 'active'))->exists() ? 'Registered' : 'Not Registered') : 'N/A',
            'registered_courses_count' => $student ? $student->courseRegistrations()->whereHas('academicSession', fn ($q) => $q->where('status', 'active'))->count() : 0,
            'current_level' => ($student && $activeSession) ? $student->currentLevel($activeSession) : '—',
            'paid_invoices_count' => $invoices->where('status', 'paid')->count(),
            'partial_invoices_count' => $invoices->where('status', 'partial')->count(),
            'unpaid_invoices_count' => $invoices->where('status', 'unpaid')->count(),
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
            'pass_rate' => $passRate,
            'profile_completion' => $student?->completion_percentage ?? 0,
            'overall_attendance' => $student?->getAttendancePercentage() ?? 0,
            'today_timetable_count' => $todayTimetableCount,
            'hasCaAddon' => $hasCaAddon,
            'coins_balance' => $coinsBalance,
            'active_placement' => $student ? StudentPlacement::where('student_id', $student->id)->latest('id')->first() : null,
            'active_project' => $student ? StudentProject::where('student_id', $student->id)->latest('id')->first() : null,
            'latest_id_card_request' => IdCardRequest::where('user_id', $user->id)->latest('id')->first(),
        ];

        return view('livewire.pages.cms.dashboards.student-dashboard', $stats);
    }
}
