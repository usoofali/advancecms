<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ProjectSession;
use App\Models\StudentProject;
use App\Models\Department;
use App\Models\Program;
use App\Models\Staff;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public ?int $selectedSessionId = null;
    public ?int $department_id = null;
    public ?int $program_id = null;
    public ?int $supervisor_id = null;
    public string $status_filter = '';

    public function mount(): void
    {
        $institutionId = auth()->user()->institution_id;
        $activeSession = ProjectSession::where('institution_id', $institutionId)
            ->where('status', 'Active')
            ->first();

        $this->selectedSessionId = $activeSession?->id ?? ProjectSession::where('institution_id', $institutionId)->first()?->id;
    }

    public function with(): array
    {
        $institutionId = auth()->user()->institution_id;
        $sessions = ProjectSession::where('institution_id', $institutionId)->latest()->get();
        $departments = Department::where('institution_id', $institutionId)->get();
        $programs = $this->department_id
            ? Program::where('department_id', $this->department_id)->get()
            : Program::where('institution_id', $institutionId)->get();
        $supervisors = Staff::where('institution_id', $institutionId)->get();

        $projectsQuery = StudentProject::with(['student', 'department', 'program', 'supervisor', 'approvedTopic', 'currentStage'])
            ->when($this->selectedSessionId, fn($q) => $q->where('project_session_id', $this->selectedSessionId))
            ->when($this->department_id, fn($q) => $q->where('department_id', $this->department_id))
            ->when($this->program_id, fn($q) => $q->where('program_id', $this->program_id))
            ->when($this->supervisor_id, fn($q) => $q->where('supervisor_id', $this->supervisor_id))
            ->when($this->status_filter, fn($q) => $q->where('overall_status', $this->status_filter));

        $projects = $projectsQuery->paginate(20);

        // Supervisor Workload Distribution
        $supervisorWorkloads = StudentProject::selectRaw('supervisor_id, count(*) as count')
            ->where('institution_id', $institutionId)
            ->when($this->selectedSessionId, fn($q) => $q->where('project_session_id', $this->selectedSessionId))
            ->whereNotNull('supervisor_id')
            ->groupBy('supervisor_id')
            ->with('supervisor')
            ->get();

        return compact('sessions', 'departments', 'programs', 'supervisors', 'projects', 'supervisorWorkloads');
    }
};
?>

<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Academic Project Reports & Audit</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Institutional project monitoring, stage completion stats, and supervisor workload analysis.</p>
        </div>

        <div class="flex items-center gap-3">
            <flux:button href="{{ route('cms.projects.print-report', ['session_id' => $selectedSessionId, 'dept_id' => $department_id, 'prog_id' => $program_id, 'supervisor_id' => $supervisor_id, 'status' => $status_filter]) }}" target="_blank" variant="primary" icon="printer">
                Print Supervision Report
            </flux:button>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <flux:select wire:model.live="selectedSessionId" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Project Sessions</option>
            @foreach($sessions as $sess)
                <option value="{{ $sess->id }}">{{ $sess->title }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="department_id" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="program_id" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Programs</option>
            @foreach($programs as $prog)
                <option value="{{ $prog->id }}">{{ $prog->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="supervisor_id" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Supervisors</option>
            @foreach($supervisors as $sup)
                <option value="{{ $sup->id }}">{{ $sup->first_name }} {{ $sup->last_name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="status_filter" class="!text-gray-900 dark:!text-white font-semibold">
            <option value="">All Statuses</option>
            <option value="Topic Pending">Topic Pending</option>
            <option value="Topic Approved">Topic Approved</option>
            <option value="In Progress">In Progress</option>
            <option value="Corrections Required">Corrections Required</option>
            <option value="Completed">Completed</option>
        </flux:select>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Report Data Table -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <flux:table :paginate="$projects" class="[&_td:first-child]:!ps-6 [&_td:last-child]:!pe-6 [&_th:first-child]:!ps-6 [&_th:last-child]:!pe-6 [&_td]:!px-6 [&_th]:!px-6 [&_td]:!py-4 [&_th]:!py-3.5">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Program</flux:table.column>
                    <flux:table.column>Supervisor</flux:table.column>
                    <flux:table.column>Current Stage</flux:table.column>
                    <flux:table.column>Progress</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($projects as $proj)
                        <flux:table.row :key="$proj->id">
                            <flux:table.cell>
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $proj->student->first_name }} {{ $proj->student->last_name }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $proj->student->matric_number }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="px-2.5 py-1 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">
                                    {{ $proj->program->acronym ?? $proj->program->name }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-xs text-gray-700 dark:text-gray-300">
                                    {{ $proj->supervisor ? $proj->supervisor->first_name . ' ' . $proj->supervisor->last_name : 'Unassigned' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ $proj->currentStage?->title ?: 'N/A' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $proj->progress_percentage }}%</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :variant="$proj->overall_status === 'Completed' ? 'success' : 'warning'">
                                    {{ $proj->overall_status }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-8 text-gray-500">
                                No records match the report criteria.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <!-- Supervisor Workload Summary Sidebar -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">Supervisor Workload Tally</h3>

            <div class="space-y-3">
                @forelse($supervisorWorkloads as $wl)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-zinc-800/40">
                        <div class="text-xs">
                            <div class="font-bold text-gray-900 dark:text-white">{{ $wl->supervisor?->first_name }} {{ $wl->supervisor?->last_name }}</div>
                            <div class="text-gray-500">{{ $wl->supervisor?->department?->name ?? 'Staff' }}</div>
                        </div>
                        <flux:badge variant="info">{{ $wl->count }} Students</flux:badge>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-6">No supervisor workload tallies available.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
