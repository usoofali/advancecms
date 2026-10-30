<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ProjectSession;
use App\Models\StudentProject;
use App\Models\ProjectTopic;
use App\Models\Student;
use App\Models\Staff;
use App\Models\Department;
use App\Models\Program;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public ?int $selectedSessionId = null;
    public string $activeTab = 'matrix'; // 'matrix', 'register', 'topics', 'supervisors'

    // Filters
    public ?int $department_id = null;
    public ?int $program_id = null;
    public ?int $supervisor_id = null;
    public string $status_filter = '';
    public string $search = '';

    // Actions state
    public bool $showTopicReviewModal = false;
    public ?int $reviewingProjectId = null;
    public ?int $selectedTopicIdToApprove = null;
    public string $topic_action = 'Approved'; // 'Approved', 'Modification Requested', 'Revoked'
    public ?string $topic_feedback = null;
    public string $topic_filter = 'all'; // 'all', 'pending', 'approved'

    public bool $showSupervisorModal = false;
    public array $selectedProjectIds = [];
    public ?int $assign_supervisor_id = null;

    public function mount(): void
    {
        $institutionId = auth()->user()->institution_id;
        $activeSession = ProjectSession::where('institution_id', $institutionId)
            ->where('status', 'Active')
            ->first();

        $this->selectedSessionId = $activeSession?->id ?? ProjectSession::where('institution_id', $institutionId)->first()?->id;
    }

    public function updatedSelectedSessionId(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = null;
        $this->resetPage();
    }

    public function updatedTopicFilter(): void
    {
        $this->resetPage('topicsPage');
    }

    public function registerStudent(Student $student): void
    {
        if (! $this->selectedSessionId) {
            Flux::toast('Please select an active project session first.', variant: 'warning');
            return;
        }

        $session = ProjectSession::find($this->selectedSessionId);
        $eligibility = StudentProject::checkEligibility($student, $session->academicSession);

        if (! $eligibility['eligible']) {
            Flux::toast($eligibility['reason'], variant: 'danger');
            return;
        }

        $exists = StudentProject::where('project_session_id', $this->selectedSessionId)
            ->where('student_id', $student->id)
            ->exists();

        if ($exists) {
            Flux::toast('Student is already registered for this project session.', variant: 'warning');
            return;
        }

        $firstStage = $session->stages()->orderBy('stage_order')->first();

        $project = StudentProject::create([
            'institution_id' => auth()->user()->institution_id,
            'project_session_id' => $this->selectedSessionId,
            'student_id' => $student->id,
            'department_id' => $student->department?->id ?? $student->program?->department_id,
            'program_id' => $student->program_id,
            'current_stage_id' => $firstStage?->id,
            'overall_status' => 'Topic Pending',
            'progress_percentage' => 0,
        ]);

        $project->logActivity('Registered for Project Session', 'Student registered for ' . $session->title);

        Flux::toast('Student successfully registered for project session.', variant: 'success');
    }

    public function openTopicReview(int|ProjectTopic $topic): void
    {
        $topicModel = $topic instanceof ProjectTopic ? $topic : ProjectTopic::findOrFail($topic);
        $this->openProjectTopicsReview($topicModel->studentProject);
        $this->selectedTopicIdToApprove = $topicModel->id;
    }

    public function processTopicReview(): void
    {
        $this->processProjectTopicsReview();
    }

    public function openProjectTopicsReview(int|StudentProject $project): void
    {
        $projectModel = $project instanceof StudentProject ? $project : StudentProject::findOrFail($project);
        $this->reviewingProjectId = $projectModel->id;
        $this->selectedTopicIdToApprove = $projectModel->approved_topic_id
            ?? $projectModel->topics()->where('status', 'Submitted')->first()?->id
            ?? $projectModel->topics()->first()?->id;
        $this->topic_action = 'Approved';
        $this->topic_feedback = $projectModel->approvedTopic?->feedback ?? '';
        $this->showTopicReviewModal = true;
    }

    public function processProjectTopicsReview(): void
    {
        if (! $this->reviewingProjectId) return;

        $project = StudentProject::with('topics')->findOrFail($this->reviewingProjectId);

        if ($this->topic_action === 'Approved') {
            if (! $this->selectedTopicIdToApprove) {
                Flux::toast('Please select a topic option to approve.', variant: 'warning');
                return;
            }

            $project->approveTopic($this->selectedTopicIdToApprove, $this->topic_feedback);
            Flux::toast('Selected topic approved successfully.', variant: 'success');
        } elseif ($this->topic_action === 'Revoked') {
            $project->revokeTopicApproval($this->topic_feedback);
            Flux::toast('Topic approval revoked successfully. Project reset for topic review.', variant: 'success');
        } else {
            $project->requestTopicModification($this->topic_feedback);
            Flux::toast('Topic modification requested from student.', variant: 'success');
        }

        $this->showTopicReviewModal = false;
    }

    public function revokeTopicApproval(int $projectId): void
    {
        $project = StudentProject::with('topics')->findOrFail($projectId);
        $project->revokeTopicApproval();
        Flux::toast('Topic approval revoked successfully.', variant: 'success');
    }

    public function openAssignSupervisor(array $projectIds): void
    {
        $this->selectedProjectIds = $projectIds;
        $this->assign_supervisor_id = count($projectIds) === 1 
            ? StudentProject::find($projectIds[0])?->supervisor_id 
            : null;
        $this->showSupervisorModal = true;
    }

    public function assignSupervisor(): void
    {
        $this->validate([
            'assign_supervisor_id' => 'required|exists:staff,id',
        ]);

        $staff = Staff::find($this->assign_supervisor_id);

        StudentProject::whereIn('id', $this->selectedProjectIds)->each(function ($project) use ($staff) {
            $isReassignment = $project->supervisor_id && $project->supervisor_id !== $staff->id;
            $oldName = $project->supervisor ? "{$project->supervisor->first_name} {$project->supervisor->last_name}" : null;

            $project->update(['supervisor_id' => $staff->id]);

            if ($isReassignment) {
                $project->logActivity('Supervisor Reassigned', "Reassigned from {$oldName} to supervisor {$staff->first_name} {$staff->last_name}");
            } else {
                $project->logActivity('Supervisor Assigned', "Assigned to supervisor {$staff->first_name} {$staff->last_name}");
            }
        });

        $this->showSupervisorModal = false;
        $this->selectedProjectIds = [];
        Flux::toast('Supervisor updated successfully.', variant: 'success');
    }

    public function with(): array
    {
        $institutionId = auth()->user()->institution_id;
        $sessions = ProjectSession::where('institution_id', $institutionId)->latest()->get();
        $departments = Department::where('institution_id', $institutionId)->get();
        $programs = $this->department_id
            ? Program::where('department_id', $this->department_id)->get()
            : Program::where('institution_id', $institutionId)->get();
        $staffMembers = Staff::where('institution_id', $institutionId)->get();

        $session = $this->selectedSessionId ? ProjectSession::find($this->selectedSessionId) : null;

        $stats = [
            'total_students' => $session ? StudentProject::where('project_session_id', $session->id)->count() : 0,
            'approved_topics' => $session ? StudentProject::where('project_session_id', $session->id)->whereNotNull('approved_topic_id')->count() : 0,
            'unassigned_supervisor' => $session ? StudentProject::where('project_session_id', $session->id)->whereNull('supervisor_id')->count() : 0,
            'completed' => $session ? StudentProject::where('project_session_id', $session->id)->where('overall_status', 'Completed')->count() : 0,
        ];

        // Matrix Query
        $projectsQuery = StudentProject::with(['student', 'department', 'program', 'supervisor', 'approvedTopic', 'currentStage'])
            ->when($this->selectedSessionId, fn($q) => $q->where('project_session_id', $this->selectedSessionId))
            ->when($this->department_id, fn($q) => $q->where('department_id', $this->department_id))
            ->when($this->program_id, fn($q) => $q->where('program_id', $this->program_id))
            ->when($this->supervisor_id, fn($q) => $q->where('supervisor_id', $this->supervisor_id))
            ->when($this->status_filter, fn($q) => $q->where('overall_status', $this->status_filter))
            ->when($this->search, function ($q) {
                $q->whereHas('student', function ($sq) {
                    $sq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('matric_number', 'like', "%{$this->search}%");
                });
            });

        $projects = $projectsQuery->paginate(15);

        // Eligible Students Query for Register tab
        $unregisteredStudents = [];
        if ($this->activeTab === 'register' && $session) {
            $registeredIds = StudentProject::where('project_session_id', $session->id)->pluck('student_id');
            
            $unregisteredStudents = Student::with(['department', 'program'])
                ->where('institution_id', $institutionId)
                ->whereNotIn('id', $registeredIds)
                ->when($this->department_id, fn($q) => $q->whereHas('program', fn($pq) => $pq->where('department_id', $this->department_id)))
                ->when($this->program_id, fn($q) => $q->where('program_id', $this->program_id))
                ->when($this->search, function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('matric_number', 'like', "%{$this->search}%");
                })
                ->paginate(15, ['*'], 'eligiblePage');
        }

        // Project Topics Query (Grouped by Student Project)
        $pendingProjectTopics = [];
        if ($this->activeTab === 'topics' && $session) {
            $pendingProjectTopics = StudentProject::with(['student', 'program', 'topics', 'approvedTopic'])
                ->where('project_session_id', $session->id)
                ->when($this->topic_filter === 'pending', fn($q) => $q->whereNull('approved_topic_id')->whereHas('topics', fn($t) => $t->where('status', 'Submitted')))
                ->when($this->topic_filter === 'approved', fn($q) => $q->whereNotNull('approved_topic_id'))
                ->when($this->topic_filter === 'all', fn($q) => $q->has('topics'))
                ->when($this->department_id, fn($q) => $q->where('department_id', $this->department_id))
                ->when($this->program_id, fn($q) => $q->where('program_id', $this->program_id))
                ->when($this->search, function ($q) {
                    $q->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('matric_number', 'like', "%{$this->search}%");
                    });
                })
                ->latest()
                ->paginate(15, ['*'], 'topicsPage');
        }

        return compact('sessions', 'departments', 'programs', 'staffMembers', 'session', 'stats', 'projects', 'unregisteredStudents', 'pendingProjectTopics');
    }
};
?>

<div>
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Academic Project Management</h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Coordinator oversight, student registration, topic approvals, and supervisor assignments.</p>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <flux:select wire:model.live="selectedSessionId" class="w-full md:w-64 !text-gray-900 dark:!text-white font-semibold">
                <option value="">Select Project Session</option>
                @foreach($sessions as $sess)
                    <option value="{{ $sess->id }}">{{ $sess->title }} ({{ $sess->status }})</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Total Registered</p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total_students'] }}</p>
                </div>
                <div class="p-2.5 sm:p-3 bg-blue-100 text-blue-600 rounded-full dark:bg-blue-900/50 dark:text-blue-300">
                    <flux:icon.users class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Approved Topics</p>
                    <p class="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats['approved_topics'] }}</p>
                </div>
                <div class="p-2.5 sm:p-3 bg-emerald-100 text-emerald-600 rounded-full dark:bg-emerald-900/50 dark:text-emerald-300">
                    <flux:icon.check-circle class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Awaiting Supervisor</p>
                    <p class="text-2xl sm:text-3xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $stats['unassigned_supervisor'] }}</p>
                </div>
                <div class="p-2.5 sm:p-3 bg-amber-100 text-amber-600 rounded-full dark:bg-amber-900/50 dark:text-amber-300">
                    <flux:icon.user-plus class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Completed Projects</p>
                    <p class="text-2xl sm:text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $stats['completed'] }}</p>
                </div>
                <div class="p-2.5 sm:p-3 bg-indigo-100 text-indigo-600 rounded-full dark:bg-indigo-900/50 dark:text-indigo-300">
                    <flux:icon.academic-cap class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-gray-200 dark:border-gray-800 mb-6 gap-3 sm:gap-6 overflow-x-auto whitespace-nowrap scrollbar-none pb-1">
        <button wire:click="$set('activeTab', 'matrix')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'matrix' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Supervision Matrix & Progress
        </button>
        <button wire:click="$set('activeTab', 'register')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'register' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Student Eligibility & Registration
        </button>
        <button wire:click="$set('activeTab', 'topics')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'topics' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Topic Review Portal
        </button>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search student name or matric..." icon="magnifying-glass" />

        <flux:select wire:model.live="department_id" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="program_id" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Programs</option>
            @foreach($programs as $prog)
                <option value="{{ $prog->id }}">{{ $prog->name }} ({{ $prog->duration_years }} Yrs)</option>
            @endforeach
        </flux:select>

        @if($activeTab === 'matrix')
            <flux:select wire:model.live="status_filter" class="!text-gray-900 dark:!text-white font-semibold">
                <option value="">All Statuses</option>
                <option value="Topic Pending">Topic Pending</option>
                <option value="Topic Approved">Topic Approved</option>
                <option value="In Progress">In Progress</option>
                <option value="Corrections Required">Corrections Required</option>
                <option value="Completed">Completed</option>
            </flux:select>
        @endif
    </div>

    <!-- Tab 1: Supervision Matrix -->
    @if($activeTab === 'matrix')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-x-auto min-w-full">
            <flux:table :paginate="$projects" class="[&_td:first-child]:!ps-6 [&_td:last-child]:!pe-6 [&_th:first-child]:!ps-6 [&_th:last-child]:!pe-6 [&_td]:!px-6 [&_th]:!px-6 [&_td]:!py-4 [&_th]:!py-3.5">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Program & Dept</flux:table.column>
                    <flux:table.column>Approved Topic</flux:table.column>
                    <flux:table.column>Supervisor</flux:table.column>
                    <flux:table.column>Progress</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($projects as $proj)
                        <flux:table.row :key="$proj->id">
                            <flux:table.cell>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-xs sm:text-sm">{{ $proj->student->first_name }} {{ $proj->student->last_name }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $proj->student->matric_number }}</div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div>
                                    <span class="px-2.5 py-1 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">
                                        {{ $proj->program->acronym ?? $proj->program->name }}
                                    </span>
                                    <div class="text-xs text-gray-400 mt-1">{{ $proj->department->name }}</div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="max-w-xs text-xs truncate font-medium text-gray-900 dark:text-gray-200">
                                    {{ $proj->approvedTopic?->topic_title ?: 'Awaiting Approval' }}
                                </div>
                                @if($proj->topics->isNotEmpty())
                                    <div class="mt-1">
                                        <button wire:click="openProjectTopicsReview({{ $proj->id }})" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium hover:underline flex items-center gap-0.5">
                                            <flux:icon.pencil-square class="w-3 h-3" />
                                            <span>{{ $proj->approved_topic_id ? 'Change / Revoke' : 'Review Topics' }}</span>
                                        </button>
                                    </div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                @if($proj->supervisor)
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-gray-800 dark:text-gray-200">
                                            {{ $proj->supervisor->first_name }} {{ $proj->supervisor->last_name }}
                                        </span>
                                        <button wire:click="openAssignSupervisor([{{ $proj->id }}])" title="Change / Reassign Supervisor" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium hover:underline flex items-center gap-0.5 shrink-0">
                                            <flux:icon.pencil-square class="w-3.5 h-3.5" />
                                            <span>Change</span>
                                        </button>
                                    </div>
                                @else
                                    <button wire:click="openAssignSupervisor([{{ $proj->id }}])" class="text-xs text-amber-600 dark:text-amber-400 font-medium hover:underline flex items-center gap-1">
                                        <flux:icon.user-plus class="w-3.5 h-3.5" /> Assign
                                    </button>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="w-28 sm:w-32">
                                    <div class="flex justify-between text-xs mb-1 font-medium text-gray-600 dark:text-gray-400">
                                        <span>{{ $proj->progress_percentage }}%</span>
                                        <span>{{ $proj->currentStage?->title ?: 'N/A' }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                        <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $proj->progress_percentage }}%"></div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :variant="$proj->overall_status === 'Completed' ? 'success' : ($proj->overall_status === 'Topic Approved' ? 'info' : 'warning')">
                                    {{ $proj->overall_status }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button href="{{ route('cms.projects.show', $proj->id) }}" variant="subtle" size="sm" icon="eye">
                                    Workspace
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center py-8 text-gray-500">
                                No registered student projects found matching filters.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <!-- Tab 2: Register Eligible Students -->
    @if($activeTab === 'register')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 overflow-x-auto min-w-full">
            <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-1">Student Eligibility Verification & Registration</h3>
            <p class="text-xs text-gray-500 mb-6">Eligible students are calculated based on final year level corresponding to program duration (e.g. 2-Yr program = 200 Level).</p>

            <flux:table :paginate="$unregisteredStudents" class="[&_td:first-child]:!ps-6 [&_td:last-child]:!pe-6 [&_th:first-child]:!ps-6 [&_th:last-child]:!pe-6 [&_td]:!px-6 [&_th]:!px-6 [&_td]:!py-4 [&_th]:!py-3.5">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Program</flux:table.column>
                    <flux:table.column>Calculated Level</flux:table.column>
                    <flux:table.column>Eligibility Status</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @if($session)
                        @forelse($unregisteredStudents as $std)
                            @php
                                $check = App\Models\StudentProject::checkEligibility($std, $session->academicSession);
                            @endphp
                            <flux:table.row :key="$std->id">
                                <flux:table.cell>
                                    <div class="font-semibold text-gray-900 dark:text-white text-xs sm:text-sm">{{ $std->first_name }} {{ $std->last_name }}</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ $std->matric_number }}</div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div>
                                        <span class="px-2.5 py-1 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">
                                            {{ $std->program?->acronym ?? $std->program?->name }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">{{ $std->program?->duration_years }} Year Duration</div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <span class="font-bold text-xs text-indigo-600 dark:text-indigo-400">
                                        {{ $check['current_level'] ? $check['current_level'] . ' Level' : 'Unknown' }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($check['eligible'])
                                        <flux:badge variant="success" icon="check-circle">Eligible Final Year</flux:badge>
                                    @else
                                        <flux:badge variant="danger" icon="x-circle">Ineligible</flux:badge>
                                        <div class="text-[10px] text-gray-500 mt-1 max-w-xs">{{ $check['reason'] }}</div>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if($check['eligible'])
                                        <flux:button wire:click="registerStudent({{ $std->id }})" variant="primary" size="sm" icon="plus">
                                            Register
                                        </flux:button>
                                    @else
                                        <flux:button disabled variant="subtle" size="sm">
                                            Ineligible
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-8 text-gray-500">
                                    All eligible students in this program/department are already registered for the active session.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    @endif
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <!-- Tab 3: Topic Review Portal (Grouped by Student Project) -->
    @if($activeTab === 'topics')
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 p-4 sm:p-6 overflow-x-auto min-w-full">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-1">Student Project Topics Review</h3>
                    <p class="text-xs text-gray-500">Review submitted proposed topics, approve official selections, or change/revoke existing approvals.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="$set('topic_filter', 'all')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {{ $topic_filter === 'all' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50' }}">All ({{ $session ? \App\Models\StudentProject::where('project_session_id', $session->id)->has('topics')->count() : 0 }})</button>
                    <button wire:click="$set('topic_filter', 'pending')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {{ $topic_filter === 'pending' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50' }}">Pending Review</button>
                    <button wire:click="$set('topic_filter', 'approved')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {{ $topic_filter === 'approved' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-zinc-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50' }}">Approved</button>
                </div>
            </div>

            <flux:table :paginate="$pendingProjectTopics" class="[&_td:first-child]:!ps-6 [&_td:last-child]:!pe-6 [&_th:first-child]:!ps-6 [&_th:last-child]:!pe-6 [&_td]:!px-6 [&_th]:!px-6 [&_td]:!py-4 [&_th]:!py-3.5">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Program</flux:table.column>
                    <flux:table.column>Submitted Topic Choices</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($pendingProjectTopics as $projTopicRow)
                        <flux:table.row :key="$projTopicRow->id">
                            <flux:table.cell>
                                <div class="font-semibold text-gray-900 dark:text-white text-xs sm:text-sm">{{ $projTopicRow->student->first_name }} {{ $projTopicRow->student->last_name }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $projTopicRow->student->matric_number }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="px-5 py-3.5">
                                <span class="px-2.5 py-1 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">
                                    {{ $projTopicRow->program->acronym ?? $projTopicRow->program->name }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell class="px-5 py-3.5">
                                <div class="space-y-1.5 max-w-md">
                                    @foreach($projTopicRow->topics as $tpc)
                                        <div class="text-xs flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 truncate">
                                                <span class="font-bold text-indigo-600 dark:text-indigo-400 shrink-0">Opt {{ $tpc->topic_order }}:</span>
                                                <span class="truncate text-gray-800 dark:text-gray-200 font-medium">{{ $tpc->topic_title }}</span>
                                            </div>
                                            <flux:badge :variant="$tpc->status === 'Approved' ? 'success' : ($tpc->status === 'Rejected' ? 'danger' : 'warning')" size="sm" class="shrink-0">
                                                {{ $tpc->status }}
                                            </flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="px-5 py-3.5">
                                <flux:badge :variant="$projTopicRow->approved_topic_id ? 'success' : 'warning'">
                                    {{ $projTopicRow->approved_topic_id ? 'Topic Approved' : 'Pending Review' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    @if($projTopicRow->approved_topic_id)
                                        <flux:button wire:click="openProjectTopicsReview({{ $projTopicRow->id }})" variant="subtle" size="sm" icon="pencil-square">
                                            Change Topic
                                        </flux:button>
                                        <flux:button wire:click="revokeTopicApproval({{ $projTopicRow->id }})" wire:confirm="Are you sure you want to revoke approval for this topic?" variant="danger" size="sm" icon="arrow-path" title="Revoke Approval">
                                            Revoke
                                        </flux:button>
                                    @else
                                        <flux:button wire:click="openProjectTopicsReview({{ $projTopicRow->id }})" variant="primary" size="sm" icon="pencil-square">
                                            Review Topics
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center py-8 text-gray-500">
                                No student topics matching the selected filter.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <!-- Topic Review Modal -->
    <flux:modal wire:model="showTopicReviewModal" class="w-full max-w-full sm:max-w-xl">
        @php
            $reviewProject = $reviewingProjectId ? App\Models\StudentProject::with(['student', 'program', 'topics', 'approvedTopic'])->find($reviewingProjectId) : null;
        @endphp

        @if($reviewProject)
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Review Topics for {{ $reviewProject->student->first_name }} {{ $reviewProject->student->last_name }}</h3>
                    <p class="text-xs text-gray-500">Matric: {{ $reviewProject->student->matric_number }} | Program: {{ $reviewProject->program->acronym ?? $reviewProject->program->name }}</p>
                </div>

                @if($reviewProject->approvedTopic)
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs text-emerald-900 dark:text-emerald-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold">Currently Approved:</span> Option {{ $reviewProject->approvedTopic->topic_order }} — {{ $reviewProject->approvedTopic->topic_title }}
                        </div>
                        <flux:badge variant="success" size="sm">Approved</flux:badge>
                    </div>
                @endif

                <div class="space-y-4">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-400">Proposed Topics Submitted:</label>

                    <div class="space-y-3">
                        @foreach($reviewProject->topics as $tpc)
                            <label class="p-3 rounded-lg border flex items-start gap-3 cursor-pointer transition {{ $selectedTopicIdToApprove == $tpc->id ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-gray-200 dark:border-gray-800' }}">
                                <input type="radio" wire:model="selectedTopicIdToApprove" value="{{ $tpc->id }}" name="topic_option" class="mt-1 text-indigo-600">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-sm text-gray-900 dark:text-white">Option {{ $tpc->topic_order }}: {{ $tpc->topic_title }}</span>
                                        <flux:badge :variant="$tpc->status === 'Approved' ? 'success' : ($tpc->status === 'Rejected' ? 'danger' : 'warning')" size="sm">
                                            {{ $tpc->status }}
                                        </flux:badge>
                                    </div>
                                    @if($tpc->description)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $tpc->description }}</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <flux:select wire:model="topic_action" label="Coordinator Decision">
                        <option value="Approved">{{ $reviewProject->approved_topic_id ? 'Approve Selected Option (Change Approval)' : 'Approve Selected Option as Official Topic' }}</option>
                        <option value="Modification Requested">Request Topic Modification from Student</option>
                        @if($reviewProject->approved_topic_id)
                            <option value="Revoked">Revoke Topic Approval (Reset to Pending)</option>
                        @endif
                    </flux:select>

                    <flux:textarea wire:model="topic_feedback" label="Remarks / Feedback Comments" rows="3" placeholder="Enter comments or instructions for student..." />
                </div>

                <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        @if($reviewProject->approved_topic_id)
                            <flux:button wire:click="revokeTopicApproval({{ $reviewProject->id }})" wire:confirm="Are you sure you want to revoke approval for this topic?" variant="danger" size="sm" icon="arrow-path">
                                Revoke Approval
                            </flux:button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button wire:click="$set('showTopicReviewModal', false)" variant="subtle">Cancel</flux:button>
                        <flux:button wire:click="processProjectTopicsReview" variant="primary">Submit Decision</flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Assign / Reassign Supervisor Modal -->
    <flux:modal wire:model="showSupervisorModal" class="w-full max-w-full sm:max-w-md">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ (count($selectedProjectIds) === 1 && StudentProject::find($selectedProjectIds[0] ?? 0)?->supervisor_id) ? 'Change / Reassign Supervisor' : 'Assign Project Supervisor' }}
                </h3>
                <p class="text-xs text-gray-500">Select a staff member to supervise the selected student project(s).</p>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="assign_supervisor_id" label="Supervisor Staff Member" class="!text-gray-900 dark:!text-white font-semibold">
                    <option value="">Select Lecturer / Staff</option>
                    @foreach($staffMembers as $stf)
                        <option value="{{ $stf->id }}">{{ $stf->first_name }} {{ $stf->last_name }} ({{ $stf->department?->name ?? 'Staff' }})</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <flux:button wire:click="$set('showSupervisorModal', false)" variant="subtle">Cancel</flux:button>
                <flux:button wire:click="assignSupervisor" variant="primary">
                    {{ (count($selectedProjectIds) === 1 && StudentProject::find($selectedProjectIds[0] ?? 0)?->supervisor_id) ? 'Update Supervisor' : 'Assign Supervisor' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
