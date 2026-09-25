<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ProjectSession;
use App\Models\StudentProject;
use App\Models\Department;
use App\Models\Program;
use App\Models\Staff;

new #[Layout('layouts.guest')] class extends Component {
    public ?int $session_id = null;
    public ?int $dept_id = null;
    public ?int $prog_id = null;
    public ?int $supervisor_id = null;
    public string $status = '';

    public function mount(): void
    {
        $this->session_id = request()->query('session_id') ? (int) request()->query('session_id') : null;
        $this->dept_id = request()->query('dept_id') ? (int) request()->query('dept_id') : null;
        $this->prog_id = request()->query('prog_id') ? (int) request()->query('prog_id') : null;
        $this->supervisor_id = request()->query('supervisor_id') ? (int) request()->query('supervisor_id') : null;
        $this->status = request()->query('status') ?? '';
    }

    public function with(): array
    {
        $institution = auth()->user()->institution;

        $session = $this->session_id ? ProjectSession::find($this->session_id) : null;
        $department = $this->dept_id ? Department::find($this->dept_id) : null;
        $program = $this->prog_id ? Program::find($this->prog_id) : null;
        $supervisor = $this->supervisor_id ? Staff::find($this->supervisor_id) : null;

        $projects = StudentProject::with(['student', 'department', 'program', 'supervisor', 'approvedTopic', 'currentStage'])
            ->where('institution_id', $institution->id)
            ->when($this->session_id, fn($q) => $q->where('project_session_id', $this->session_id))
            ->when($this->dept_id, fn($q) => $q->where('department_id', $this->dept_id))
            ->when($this->prog_id, fn($q) => $q->where('program_id', $this->prog_id))
            ->when($this->supervisor_id, fn($q) => $q->where('supervisor_id', $this->supervisor_id))
            ->when($this->status, fn($q) => $q->where('overall_status', $this->status))
            ->get();

        return compact('institution', 'session', 'department', 'program', 'supervisor', 'projects');
    }
};
?>

<div class="max-w-5xl mx-auto p-8 bg-white text-gray-900 min-h-screen">
    <!-- Header -->
    <div class="text-center border-b pb-6 mb-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide">{{ $institution->name }}</h1>
        <h2 class="text-lg font-semibold text-gray-700 mt-1">ACADEMIC PROJECT SUPERVISION & MONITORING REPORT</h2>
        <p class="text-xs text-gray-500 mt-1">Generated Date: {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <!-- Metadata Grid -->
    <div class="grid grid-cols-2 gap-4 text-xs mb-6 p-4 bg-gray-50 border rounded-lg">
        <div>
            <span class="font-bold">Project Session:</span> {{ $session ? $session->title : 'All Active Sessions' }}
        </div>
        <div>
            <span class="font-bold">Department:</span> {{ $department ? $department->name : 'All Departments' }}
        </div>
        <div>
            <span class="font-bold">Program:</span> {{ $program ? $program->name : 'All Programs' }}
        </div>
        <div>
            <span class="font-bold">Supervisor Filter:</span> {{ $supervisor ? $supervisor->first_name . ' ' . $supervisor->last_name : 'All Supervisors' }}
        </div>
    </div>

    <!-- Data Table -->
    <table class="w-full text-xs border-collapse border border-gray-300">
        <thead>
            <tr class="bg-gray-100 text-left font-bold border-b border-gray-300">
                <th class="p-2 border border-gray-300">#</th>
                <th class="p-2 border border-gray-300">Student Name</th>
                <th class="p-2 border border-gray-300">Matric Number</th>
                <th class="p-2 border border-gray-300">Program</th>
                <th class="p-2 border border-gray-300">Approved Topic</th>
                <th class="p-2 border border-gray-300">Supervisor</th>
                <th class="p-2 border border-gray-300">Stage</th>
                <th class="p-2 border border-gray-300">Progress</th>
                <th class="p-2 border border-gray-300">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projects as $index => $proj)
                <tr class="border-b border-gray-200">
                    <td class="px-3 py-2.5 border border-gray-300">{{ $index + 1 }}</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-semibold">{{ $proj->student->first_name }} {{ $proj->student->last_name }}</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-mono">{{ $proj->student->matric_number }}</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-bold">{{ $proj->program->acronym ?? $proj->program->name }}</td>
                    <td class="px-3 py-2.5 border border-gray-300">{{ $proj->approvedTopic?->topic_title ?: 'Pending' }}</td>
                    <td class="px-3 py-2.5 border border-gray-300">{{ $proj->supervisor ? $proj->supervisor->first_name . ' ' . $proj->supervisor->last_name : 'Unassigned' }}</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-semibold">{{ $proj->currentStage?->title ?: 'N/A' }}</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-bold">{{ $proj->progress_percentage }}%</td>
                    <td class="px-3 py-2.5 border border-gray-300 font-semibold">{{ $proj->overall_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="p-6 text-center text-gray-500">No project supervision records match this query.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer Signatures -->
    <div class="mt-16 pt-8 border-t flex justify-between text-xs text-gray-700">
        <div class="text-center w-48">
            <div class="border-b border-gray-400 mb-2 h-10"></div>
            <p class="font-bold">Project Coordinator Signature</p>
        </div>

        <div class="text-center w-48">
            <div class="border-b border-gray-400 mb-2 h-10"></div>
            <p class="font-bold">Head of Department (HOD)</p>
        </div>
    </div>
</div>
