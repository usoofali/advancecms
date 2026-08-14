<?php

use App\Actions\Placements\SubmitPlacementEvaluationAction;
use App\Models\AcademicSession;
use App\Models\StudentPlacement;
use App\Services\PlacementSupervisorResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public $sessionFilter = '';
    public $search = '';
    public $evalStatusFilter = '';

    public $showEvalModal = false;
    public ?StudentPlacement $selectedPlacement = null;

    // Evaluation form fields
    public $punctuality_rating = 5;
    public $attendance_rating = 5;
    public $conduct_discipline_rating = 5;
    public $technical_skills_rating = 5;
    public $logbook_maintenance_rating = 5;
    public $supervisor_remarks = '';

    public function mount()
    {
        $currentSession = AcademicSession::where('status', 'active')->first();
        if ($currentSession) {
            $this->sessionFilter = (string) $currentSession->id;
        }
    }

    public function openEvalModal(int $placementId)
    {
        $this->resetValidation();
        $this->selectedPlacement = StudentPlacement::with(['student.user', 'organization', 'evaluation'])->findOrFail($placementId);

        if ($this->selectedPlacement->evaluation) {
            $eval = $this->selectedPlacement->evaluation;
            $this->punctuality_rating = $eval->punctuality_rating;
            $this->attendance_rating = $eval->attendance_rating;
            $this->conduct_discipline_rating = $eval->conduct_discipline_rating;
            $this->technical_skills_rating = $eval->technical_skills_rating;
            $this->logbook_maintenance_rating = $eval->logbook_maintenance_rating;
            $this->supervisor_remarks = $eval->supervisor_remarks ?? '';
        } else {
            $this->punctuality_rating = 5;
            $this->attendance_rating = 5;
            $this->conduct_discipline_rating = 5;
            $this->technical_skills_rating = 5;
            $this->logbook_maintenance_rating = 5;
            $this->supervisor_remarks = '';
        }

        $this->showEvalModal = true;
    }

    public function submitEvaluation(SubmitPlacementEvaluationAction $action)
    {
        if (!$this->selectedPlacement) return;

        $this->validate([
            'punctuality_rating' => 'required|integer|min:1|max:5',
            'attendance_rating' => 'required|integer|min:1|max:5',
            'conduct_discipline_rating' => 'required|integer|min:1|max:5',
            'technical_skills_rating' => 'required|integer|min:1|max:5',
            'logbook_maintenance_rating' => 'required|integer|min:1|max:5',
            'supervisor_remarks' => 'nullable|string|max:1000',
        ]);

        $action->execute(
            placement: $this->selectedPlacement,
            supervisorId: Auth::id(),
            punctuality: (int) $this->punctuality_rating,
            attendance: (int) $this->attendance_rating,
            conduct: (int) $this->conduct_discipline_rating,
            technical: (int) $this->technical_skills_rating,
            logbook: (int) $this->logbook_maintenance_rating,
            remarks: $this->supervisor_remarks ?: null
        );

        $this->showEvalModal = false;
        session()->flash('success', 'Student placement evaluation saved successfully!');
    }

    public function render(PlacementSupervisorResolver $resolver)
    {
        $sessionId = $this->sessionFilter ? (int) $this->sessionFilter : null;
        $allAssignedPlacements = $resolver->getPlacementsForSupervisor(Auth::id(), $sessionId);

        $filteredPlacements = $allAssignedPlacements->filter(function ($p) {
            $matchesSearch = true;
            if ($this->search) {
                $term = strtolower($this->search);
                $name = strtolower($p->student?->user?->name ?? '');
                $matric = strtolower($p->student?->matric_number ?? '');
                $org = strtolower($p->organization?->name ?? '');
                $matchesSearch = str_contains($name, $term) || str_contains($matric, $term) || str_contains($org, $term);
            }

            $matchesEval = true;
            if ($this->evalStatusFilter === 'evaluated') {
                $matchesEval = !is_null($p->evaluation);
            } elseif ($this->evalStatusFilter === 'pending') {
                $matchesEval = is_null($p->evaluation);
            }

            return $matchesSearch && $matchesEval;
        });

        $academicSessions = AcademicSession::orderByDesc('name')->get();

        $totalAssigned = $allAssignedPlacements->count();
        $totalEvaluated = $allAssignedPlacements->filter(fn ($p) => !is_null($p->evaluation))->count();
        $totalPending = $totalAssigned - $totalEvaluated;

        return view('livewire.lecturer.placements.my-supervisions', compact(
            'filteredPlacements',
            'academicSessions',
            'totalAssigned',
            'totalEvaluated',
            'totalPending'
        ));
    }
}; ?>

<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Supervised Placements</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">View and complete standardized evaluation questionnaires for students under your supervision.</p>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="sessionFilter" placeholder="All Academic Sessions">
                <flux:select.option value="">All Academic Sessions</flux:select.option>
                @foreach($academicSessions as $session)
                    <flux:select.option value="{{ $session->id }}">{{ $session->name }} @if($session->status === 'active')(Current)@endif</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Students Assigned</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $totalAssigned }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Evaluations Completed</span>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $totalEvaluated }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Pending Evaluation</span>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $totalPending }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search student name, matric, or company..." icon="magnifying-glass" />
        </div>
        <div class="w-full sm:w-60">
            <flux:select wire:model.live="evalStatusFilter" label="Evaluation Status">
                <flux:select.option value="">All Students</flux:select.option>
                <flux:select.option value="pending">Pending Evaluation</flux:select.option>
                <flux:select.option value="evaluated">Evaluated</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Supervisees Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
                <thead class="bg-slate-50 dark:bg-slate-800/80 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Department & Program</th>
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Level</th>
                        <th class="px-4 py-3">Evaluation Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($filteredPlacements as $index => $placement)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $placement->student?->user?->name ?? 'Student' }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    {{ $placement->student?->matric_number ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $placement->student?->department?->name ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $placement->student?->program?->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5 font-medium text-slate-800 dark:text-slate-200">
                                {{ $placement->organization?->name ?? $placement->custom_organization_name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3.5 text-xs font-semibold text-slate-600 dark:text-slate-400">
                                {{ $placement->student?->level ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3.5">
                                @if($placement->evaluation)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        Grade: {{ $placement->evaluation->performance_grade }} ({{ number_format($placement->evaluation->total_score, 0) }}%)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        Pending Eval
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <flux:button wire:click="openEvalModal({{ $placement->id }})" variant="primary" size="sm" icon="pencil">
                                    {{ $placement->evaluation ? 'Edit Assessment' : 'Evaluate' }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                No students found under your supervision matching the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Evaluation Questionnaire Modal -->
    <flux:modal wire:model="showEvalModal" name="evaluation-modal" class="md:max-w-2xl">
        <div class="space-y-6">
            <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
                <flux:heading size="lg" class="text-slate-900 dark:text-white">
                    Standardized Supervision Evaluation
                </flux:heading>
                @if($selectedPlacement)
                    <flux:subheading class="mt-1">
                        Student: <strong class="text-slate-800 dark:text-slate-200">{{ $selectedPlacement->student?->user?->name }}</strong> ({{ $selectedPlacement->student?->matric_number }})
                    </flux:subheading>
                @endif
            </div>

            @if($selectedPlacement)
                <form wire:submit.prevent="submitEvaluation" class="space-y-5">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg text-xs text-indigo-900 dark:text-indigo-300">
                        Assess the student on each key performance criteria using a 1 to 5 rating scale (1 = Poor, 5 = Excellent).
                    </div>

                    <!-- Criteria Rating Items (Scrollable List) -->
                    <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-1">
                        <!-- 1. Punctuality -->
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="font-bold text-sm text-slate-800 dark:text-slate-200">1. Punctuality & Time Management</label>
                                <p class="text-xs text-slate-500">Reports to organization on time and adheres to work schedules.</p>
                            </div>
                            <div class="w-full sm:w-44 shrink-0">
                                <flux:select wire:model.live="punctuality_rating">
                                    <flux:select.option value="5">5 - Excellent</flux:select.option>
                                    <flux:select.option value="4">4 - Good</flux:select.option>
                                    <flux:select.option value="3">3 - Satisfactory</flux:select.option>
                                    <flux:select.option value="2">2 - Fair</flux:select.option>
                                    <flux:select.option value="1">1 - Poor</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <!-- 2. Attendance -->
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="font-bold text-sm text-slate-800 dark:text-slate-200">2. Attendance & Regularity</label>
                                <p class="text-xs text-slate-500">Consistent daily presence throughout placement duration.</p>
                            </div>
                            <div class="w-full sm:w-44 shrink-0">
                                <flux:select wire:model.live="attendance_rating">
                                    <flux:select.option value="5">5 - Excellent</flux:select.option>
                                    <flux:select.option value="4">4 - Good</flux:select.option>
                                    <flux:select.option value="3">3 - Satisfactory</flux:select.option>
                                    <flux:select.option value="2">2 - Fair</flux:select.option>
                                    <flux:select.option value="1">1 - Poor</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <!-- 3. Conduct & Discipline -->
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="font-bold text-sm text-slate-800 dark:text-slate-200">3. Professional Conduct & Discipline</label>
                                <p class="text-xs text-slate-500">Ethics, dress code, respect for supervisors and workplace rules.</p>
                            </div>
                            <div class="w-full sm:w-44 shrink-0">
                                <flux:select wire:model.live="conduct_discipline_rating">
                                    <flux:select.option value="5">5 - Excellent</flux:select.option>
                                    <flux:select.option value="4">4 - Good</flux:select.option>
                                    <flux:select.option value="3">3 - Satisfactory</flux:select.option>
                                    <flux:select.option value="2">2 - Fair</flux:select.option>
                                    <flux:select.option value="1">1 - Poor</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <!-- 4. Technical Skills -->
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="font-bold text-sm text-slate-800 dark:text-slate-200">4. Technical & Applied Work Skills</label>
                                <p class="text-xs text-slate-500">Ability to apply academic knowledge to practical tasks.</p>
                            </div>
                            <div class="w-full sm:w-44 shrink-0">
                                <flux:select wire:model.live="technical_skills_rating">
                                    <flux:select.option value="5">5 - Excellent</flux:select.option>
                                    <flux:select.option value="4">4 - Good</flux:select.option>
                                    <flux:select.option value="3">3 - Satisfactory</flux:select.option>
                                    <flux:select.option value="2">2 - Fair</flux:select.option>
                                    <flux:select.option value="1">1 - Poor</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <!-- 5. Logbook Maintenance -->
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="font-bold text-sm text-slate-800 dark:text-slate-200">5. Logbook Maintenance & Documentation</label>
                                <p class="text-xs text-slate-500">Regularity, detail, and accuracy of logbook entries.</p>
                            </div>
                            <div class="w-full sm:w-44 shrink-0">
                                <flux:select wire:model.live="logbook_maintenance_rating">
                                    <flux:select.option value="5">5 - Excellent</flux:select.option>
                                    <flux:select.option value="4">4 - Good</flux:select.option>
                                    <flux:select.option value="3">3 - Satisfactory</flux:select.option>
                                    <flux:select.option value="2">2 - Fair</flux:select.option>
                                    <flux:select.option value="1">1 - Poor</flux:select.option>
                                </flux:select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Supervisor Remarks & Recommendations</label>
                            <textarea wire:model="supervisor_remarks" rows="3" placeholder="Provide general feedback or observations on student performance..." class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"></textarea>
                        </div>
                    </div>

                    <!-- Calculated Score & Grade Live Preview -->
                    @php
                        $sum = (int)$punctuality_rating + (int)$attendance_rating + (int)$conduct_discipline_rating + (int)$technical_skills_rating + (int)$logbook_maintenance_rating;
                        $calcScore = round(($sum / 25) * 100, 1);
                        $calcGrade = match(true) {
                            $calcScore >= 70 => 'A',
                            $calcScore >= 60 => 'B',
                            $calcScore >= 50 => 'C',
                            $calcScore >= 45 => 'D',
                            default => 'F',
                        };
                    @endphp

                    <div class="p-4 bg-slate-900 text-white rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block">Calculated Performance</span>
                            <span class="text-xl font-bold text-emerald-400">{{ $calcScore }}% Total Score</span>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block">Grade</span>
                            <span class="text-2xl font-black text-white px-3 py-1 bg-indigo-600 rounded-lg">{{ $calcGrade }}</span>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">Submit Assessment</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
