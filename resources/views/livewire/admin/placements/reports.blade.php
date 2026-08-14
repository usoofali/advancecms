<?php

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PlacementEvaluation;
use App\Models\PlacementSupervisor;
use App\Models\Program;
use App\Models\StudentPlacement;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $sessionFilter = '';
    public $deptFilter = '';
    public $programFilter = '';
    public $levelFilter = '';
    public $orgFilter = '';
    public $supervisorFilter = '';
    public $search = '';

    public function mount()
    {
        $currentSession = AcademicSession::where('status', 'active')->first();
        if ($currentSession) {
            $this->sessionFilter = (string) $currentSession->id;
        }
    }

    public function updatedDeptFilter()
    {
        $this->programFilter = '';
    }

    public function render()
    {
        $instId = auth()->user()?->institution_id;

        $evaluationsQuery = PlacementEvaluation::query()
            ->with(['student.user', 'student.department', 'student.program', 'student.studentPlacements.organization', 'placement.organization', 'supervisor'])
            ->when($instId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('institution_id', $instId)))
            ->when($this->sessionFilter, fn ($q) => $q->where('academic_session_id', $this->sessionFilter))
            ->when($this->orgFilter, fn ($q) => $q->whereHas('placement', fn ($p) => $p->where('organization_id', $this->orgFilter)))
            ->when($this->deptFilter, fn ($q) => $q->whereHas('student.program', fn ($p) => $p->where('department_id', $this->deptFilter)))
            ->when($this->programFilter, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('program_id', $this->programFilter)))
            ->when($this->levelFilter, fn ($q) => $q->whereHas('student', function ($s) {
                $session = $this->sessionFilter ? AcademicSession::find($this->sessionFilter) : AcademicSession::where('status', 'active')->first();
                if ($session) {
                    $s->atLevel($this->levelFilter, $session);
                }
            }))
            ->when($this->supervisorFilter, fn ($q) => $q->where('supervisor_id', $this->supervisorFilter))
            ->when($this->search, function ($q) {
                $q->whereHas('student.user', fn ($u) => $u->where('name', 'like', "%{$this->search}%"))
                  ->orWhereHas('student', fn ($s) => $s->where('matric_number', 'like', "%{$this->search}%"));
            });

        $evaluations = (clone $evaluationsQuery)->latest('evaluated_at')->paginate(15);

        // Analytics calculation
        $allEvals = (clone $evaluationsQuery)->get();
        $totalEvaluations = $allEvals->count();
        $avgScore = $totalEvaluations > 0 ? round($allEvals->avg('total_score'), 1) : 0;
        $gradeCounts = [
            'A' => $allEvals->where('performance_grade', 'A')->count(),
            'B' => $allEvals->where('performance_grade', 'B')->count(),
            'C' => $allEvals->where('performance_grade', 'C')->count(),
            'D' => $allEvals->where('performance_grade', 'D')->count(),
            'F' => $allEvals->where('performance_grade', 'F')->count(),
        ];

        $academicSessions = AcademicSession::orderByDesc('name')->get();

        $departments = Department::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->orderBy('name')
            ->get();

        $programs = Program::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->when($this->deptFilter, fn ($q) => $q->where('department_id', $this->deptFilter))
            ->orderBy('name')
            ->get();

        $organizations = Organization::orderBy('name')->get();

        $supervisors = User::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->whereIn('id', PlacementSupervisor::query()->when($instId, fn ($q) => $q->where('institution_id', $instId))->select('user_id'))
            ->orderBy('name')
            ->get();

        return view('livewire.admin.placements.reports', compact(
            'evaluations',
            'totalEvaluations',
            'avgScore',
            'gradeCounts',
            'academicSessions',
            'departments',
            'programs',
            'organizations',
            'supervisors'
        ));
    }
}; ?>

<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Supervision Evaluation Reports</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">View supervision performance analytics and generate printable reports.</p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $printUrl = route('cms.placements.print-report', [
                    'session_id' => $sessionFilter,
                    'dept_id' => $deptFilter,
                    'program_id' => $programFilter,
                    'level' => $levelFilter,
                    'org_id' => $orgFilter,
                    'supervisor_id' => $supervisorFilter,
                ]);
            @endphp
            <flux:button href="{{ $printUrl }}" target="_blank" variant="primary" icon="printer">
                Printable Report
            </flux:button>
        </div>
    </div>

    <!-- Analytics Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Evaluated Students</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $totalEvaluations }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Average Performance Score</span>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $avgScore }}%</div>
        </div>
        <div class="md:col-span-2 p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm flex flex-col justify-between">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Grade Breakdown</span>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 rounded-lg text-xs font-bold">A: {{ $gradeCounts['A'] }}</span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 rounded-lg text-xs font-bold">B: {{ $gradeCounts['B'] }}</span>
                <span class="px-3 py-1 bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 rounded-lg text-xs font-bold">C: {{ $gradeCounts['C'] }}</span>
                <span class="px-3 py-1 bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 rounded-lg text-xs font-bold">D: {{ $gradeCounts['D'] }}</span>
                <span class="px-3 py-1 bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 rounded-lg text-xs font-bold">F: {{ $gradeCounts['F'] }}</span>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <flux:input wire:model.live.debounce.300ms="search" label="Search Student" placeholder="Name or Matric..." icon="magnifying-glass" />
            </div>
            <div>
                <flux:select wire:model.live="sessionFilter" label="Session">
                    <flux:select.option value="">All Sessions</flux:select.option>
                    @foreach($academicSessions as $session)
                        <flux:select.option value="{{ $session->id }}">{{ $session->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="deptFilter" label="Department">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="programFilter" label="Program">
                    <flux:select.option value="">All Programs</flux:select.option>
                    @foreach($programs as $prog)
                        <flux:select.option value="{{ $prog->id }}">{{ $prog->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="levelFilter" label="Level">
                    <flux:select.option value="">All Levels</flux:select.option>
                    <flux:select.option value="100">100 Level</flux:select.option>
                    <flux:select.option value="200">200 Level</flux:select.option>
                    <flux:select.option value="300">300 Level</flux:select.option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="supervisorFilter" label="Supervisor">
                    <flux:select.option value="">All Supervisors</flux:select.option>
                    @foreach($supervisors as $sup)
                        <flux:select.option value="{{ $sup->id }}">{{ $sup->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 text-xs font-semibold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Dept / Program / Level</th>
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Supervisor</th>
                        <th class="px-4 py-3 text-center">Score Metrics</th>
                        <th class="px-4 py-3 text-right">Total Score & Grade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @forelse($evaluations as $eval)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $eval->student?->user?->name ?? 'N/A' }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $eval->student?->matric_number }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs space-y-0.5">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $eval->student?->department?->name ?? 'N/A' }}</div>
                                <div class="text-slate-400">
                                    {{ $eval->student?->program?->name ?? 'N/A' }}
                                    @if($eval->student)
                                        <span class="font-semibold text-slate-600 dark:text-slate-300">
                                            ({{ $eval->academicSession ? $eval->student->currentLevel($eval->academicSession) : $eval->student->level }} Level)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                {{ $eval->placement?->organization_display_name ?? $eval->student?->studentPlacements?->first()?->organization_display_name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-xs font-medium text-slate-800 dark:text-slate-200">
                                {{ $eval->supervisor?->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex gap-1 text-[11px] font-medium text-slate-600 dark:text-slate-400">
                                    <span title="Punctuality">P:{{ $eval->punctuality_rating }}</span> |
                                    <span title="Attendance">A:{{ $eval->attendance_rating }}</span> |
                                    <span title="Conduct">C:{{ $eval->conduct_discipline_rating }}</span> |
                                    <span title="Technical">T:{{ $eval->technical_skills_rating }}</span> |
                                    <span title="Logbook">L:{{ $eval->logbook_maintenance_rating }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300">
                                    {{ number_format($eval->total_score, 1) }}% (Grade {{ $eval->performance_grade }})
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                                No evaluations found matching the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800">
            {{ $evaluations->links() }}
        </div>
    </div>
</div>
