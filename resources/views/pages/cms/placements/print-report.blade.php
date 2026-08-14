<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PlacementEvaluation;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Print Supervision Report')] #[Layout('layouts.guest')] class extends Component {
    public $evaluations;
    public $sessionName = 'All Sessions';
    public $deptName = 'All Departments';
    public $programName = 'All Programs';
    public $levelName = 'All Levels';
    public $orgName = 'All Organizations';
    public $supervisorName = 'All Supervisors';

    public $institution;
    public $totalCount = 0;
    public $avgScore = 0;
    public $gradeCounts = [];

    public function mount()
    {
        $sessionId = Request::query('session_id');
        $deptId = Request::query('dept_id');
        $programId = Request::query('program_id');
        $level = Request::query('level');
        $orgId = Request::query('org_id');
        $supervisorId = Request::query('supervisor_id');

        if ($sessionId) {
            $this->sessionName = AcademicSession::find($sessionId)?->name ?? 'All Sessions';
        }
        if ($deptId) {
            $this->deptName = Department::find($deptId)?->name ?? 'All Departments';
        }
        if ($programId) {
            $this->programName = Program::find($programId)?->name ?? 'All Programs';
        }
        if ($level) {
            $this->levelName = "Level {$level}";
        }
        if ($orgId) {
            $this->orgName = Organization::find($orgId)?->name ?? 'All Organizations';
        }
        if ($supervisorId) {
            $this->supervisorName = User::find($supervisorId)?->name ?? 'All Supervisors';
        }

        $instId = auth()->user()?->institution_id;
        $this->institution = $instId ? \App\Models\Institution::find($instId) : \App\Models\Institution::first();

        $query = PlacementEvaluation::query()
            ->with(['student.user', 'student.department', 'student.program', 'student.studentPlacements.organization', 'placement.organization', 'supervisor', 'academicSession'])
            ->when($instId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('institution_id', $instId)))
            ->when($sessionId, fn ($q) => $q->where('academic_session_id', $sessionId))
            ->when($orgId, fn ($q) => $q->whereHas('placement', fn ($p) => $p->where('organization_id', $orgId)))
            ->when($deptId, fn ($q) => $q->whereHas('student.program', fn ($p) => $p->where('department_id', $deptId)))
            ->when($programId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('program_id', $programId)))
            ->when($level, fn ($q) => $q->whereHas('student', function ($s) use ($sessionId) {
                $session = $sessionId ? AcademicSession::find($sessionId) : AcademicSession::where('status', 'active')->first();
                if ($session) {
                    $s->atLevel($level, $session);
                }
            }))
            ->when($supervisorId, fn ($q) => $q->where('supervisor_id', $supervisorId));

        $this->evaluations = $query->latest('evaluated_at')->get();
        $this->totalCount = $this->evaluations->count();
        $this->avgScore = $this->totalCount > 0 ? round($this->evaluations->avg('total_score'), 1) : 0;
        $this->gradeCounts = [
            'A' => $this->evaluations->where('performance_grade', 'A')->count(),
            'B' => $this->evaluations->where('performance_grade', 'B')->count(),
            'C' => $this->evaluations->where('performance_grade', 'C')->count(),
            'D' => $this->evaluations->where('performance_grade', 'D')->count(),
            'F' => $this->evaluations->where('performance_grade', 'F')->count(),
        ];
    }
}; ?>

<div class="bg-white text-slate-900 min-h-screen p-8 print:p-0">
    <!-- Non-printable top bar -->
    <div class="print:hidden mb-6 flex justify-between items-center p-4 bg-slate-100 rounded-xl border border-slate-200">
        <div>
            <span class="font-bold text-slate-800">Supervision Report Print View</span>
            <p class="text-xs text-slate-500">Press Print to generate physical copy or export to PDF.</p>
        </div>
        <flux:button onclick="window.print()" variant="primary" icon="printer">
            Print / Download PDF
        </flux:button>
    </div>

    <!-- Official Header -->
    <div class="flex flex-col items-center border-b-2 border-slate-900 pb-4 mb-6 text-center">
        @if($institution?->logo_path)
            <img src="{{ asset('storage/' . $institution->logo_path) }}" class="h-20 w-20 object-contain mb-3" alt="Institution Logo">
        @elseif($institution?->logo_url)
            <img src="{{ $institution->logo_url }}" class="h-20 w-20 object-contain mb-3" alt="Institution Logo">
        @endif
        <h1 class="text-2xl font-black uppercase tracking-tight text-slate-900">{{ $institution?->name ?? config('app.name') }}</h1>
        <h2 class="text-base font-bold uppercase tracking-widest text-slate-700 mt-1">Directorate of Industrial Attachment & SIWES</h2>
        <h3 class="text-sm font-semibold uppercase text-slate-600 mt-0.5">Student Placement Supervision & Evaluation Report</h3>
        <div class="mt-3 flex flex-wrap justify-center gap-3 text-xs font-medium text-slate-700 uppercase bg-slate-100 px-4 py-1.5 rounded-lg border border-slate-200">
            <span><strong>Session:</strong> {{ $sessionName }}</span> |
            <span><strong>Department:</strong> {{ $deptName }}</span> |
            <span><strong>Program:</strong> {{ $programName }}</span> |
            <span><strong>Level:</strong> {{ $levelName }}</span> |
            <span><strong>Organization:</strong> {{ $orgName }}</span>
        </div>
        <div class="mt-2 text-[10px] text-slate-500">
            Generated on: {{ now()->format('M d, Y H:i A') }}
        </div>
    </div>

    <!-- Report Executive Summary -->
    <div class="mb-6 p-4 bg-slate-50 border border-slate-300 rounded-lg text-xs grid grid-cols-4 gap-4 text-center">
        <div>
            <span class="block text-slate-500 font-bold uppercase">Total Evaluated</span>
            <span class="text-base font-black text-slate-900">{{ $totalCount }}</span>
        </div>
        <div>
            <span class="block text-slate-500 font-bold uppercase">Overall Mean Score</span>
            <span class="text-base font-black text-indigo-700">{{ $avgScore }}%</span>
        </div>
        <div class="col-span-2">
            <span class="block text-slate-500 font-bold uppercase mb-1">Grade Distribution</span>
            <div class="flex justify-center gap-2 font-bold text-slate-800">
                <span>A: {{ $gradeCounts['A'] }}</span> |
                <span>B: {{ $gradeCounts['B'] }}</span> |
                <span>C: {{ $gradeCounts['C'] }}</span> |
                <span>D: {{ $gradeCounts['D'] }}</span> |
                <span>F: {{ $gradeCounts['F'] }}</span>
            </div>
        </div>
    </div>

    <!-- Report Table -->
    <table class="w-full text-left text-xs border-collapse border border-slate-400">
        <thead>
            <tr class="bg-slate-200 text-slate-900 font-bold border-b border-slate-400">
                <th class="p-2 border border-slate-400 text-center w-8">#</th>
                <th class="p-2 border border-slate-400">Student Name & Matric</th>
                <th class="p-2 border border-slate-400">Department / Level</th>
                <th class="p-2 border border-slate-400">Placement Organization</th>
                <th class="p-2 border border-slate-400">Supervisor</th>
                <th class="p-2 border border-slate-400 text-center">Ratings (P/A/C/T/L)</th>
                <th class="p-2 border border-slate-400 text-right">Score</th>
                <th class="p-2 border border-slate-400 text-center">Grade</th>
            </tr>
        </thead>
        <tbody>
            @forelse($evaluations as $index => $eval)
                <tr class="border-b border-slate-300">
                    <td class="p-2 border border-slate-300 text-center font-medium">{{ $index + 1 }}</td>
                    <td class="p-2 border border-slate-300">
                        <div class="font-bold text-slate-900">{{ $eval->student?->user?->name }}</div>
                        <div class="font-mono text-[11px] text-slate-600">{{ $eval->student?->matric_number }}</div>
                    </td>
                    <td class="p-2 border border-slate-300">
                        <div class="font-medium text-slate-900">{{ $eval->student?->department?->name ?? 'N/A' }}</div>
                        <div class="text-[10px] text-slate-500">
                            {{ $eval->student?->program?->name ?? 'N/A' }}
                            @if($eval->student)
                                ({{ $eval->academicSession ? $eval->student->currentLevel($eval->academicSession) : $eval->student->level }} Level)
                            @endif
                        </div>
                    </td>
                    <td class="p-2 border border-slate-300 font-medium">
                        {{ $eval->placement?->organization_display_name ?? $eval->student?->studentPlacements?->first()?->organization_display_name ?? 'N/A' }}
                    </td>
                    <td class="p-2 border border-slate-300">
                        {{ $eval->supervisor?->name ?? 'N/A' }}
                    </td>
                    <td class="p-2 border border-slate-300 text-center font-mono">
                        {{ $eval->punctuality_rating }}/{{ $eval->attendance_rating }}/{{ $eval->conduct_discipline_rating }}/{{ $eval->technical_skills_rating }}/{{ $eval->logbook_maintenance_rating }}
                    </td>
                    <td class="p-2 border border-slate-300 text-right font-bold">
                        {{ number_format($eval->total_score, 1) }}%
                    </td>
                    <td class="p-2 border border-slate-300 text-center font-black text-sm">
                        {{ $eval->performance_grade }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="p-6 text-center text-slate-500 font-medium border border-slate-300">
                        No evaluation records found matching the specified report criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Legend & Key -->
    <div class="mt-4 text-[10px] text-slate-600 space-y-1">
        <p><strong>Rating Scale Key:</strong> P = Punctuality (1-5), A = Attendance (1-5), C = Conduct & Discipline (1-5), T = Technical Skills (1-5), L = Logbook Maintenance (1-5).</p>
        <p><strong>Grade Scale:</strong> A (>=70%), B (60-69%), C (50-59%), D (45-49%), F (<45%).</p>
    </div>

    <!-- Signatures -->
    <div class="mt-16 grid grid-cols-2 gap-12 text-xs font-bold text-slate-800">
        <div class="border-t border-slate-900 pt-2 text-center">
            <div>Institutional SIWES Supervisor / Coordinator</div>
            <div class="text-[10px] font-normal text-slate-500 mt-1">Signature & Date</div>
        </div>
        <div class="border-t border-slate-900 pt-2 text-center">
            <div>Director of Industrial Training & Placements</div>
            <div class="text-[10px] font-normal text-slate-500 mt-1">Signature & Date</div>
        </div>
    </div>
</div>
