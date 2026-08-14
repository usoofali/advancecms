<?php

use App\Actions\Placements\AssignPlacementSupervisorAction;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PlacementSupervisor;
use App\Models\Program;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $sessionFilter = '';
    public $orgFilter = '';
    public $deptFilter = '';
    public $programFilter = '';
    public $levelFilter = '';

    public $showAssignModal = false;
    public $editingId = null;

    // Form fields
    public $academic_session_id = '';
    public $organization_id = '';
    public $department_id = '';
    public $program_id = '';
    public $level = '';
    public $user_id = '';
    public $notes = '';

    public function mount()
    {
        $currentSession = AcademicSession::where('status', 'active')->first();
        if ($currentSession) {
            $this->sessionFilter = (string) $currentSession->id;
            $this->academic_session_id = (string) $currentSession->id;
        }
    }

    public function updatedDepartmentId($value)
    {
        $this->program_id = '';
    }

    public function openAssignModal(?int $id = null)
    {
        $this->resetValidation();
        $this->editingId = $id;

        if ($id) {
            $supervisor = PlacementSupervisor::findOrFail($id);
            $this->academic_session_id = (string) $supervisor->academic_session_id;
            $this->organization_id = (string) $supervisor->organization_id;
            $this->department_id = $supervisor->department_id ? (string) $supervisor->department_id : '';
            $this->program_id = $supervisor->program_id ? (string) $supervisor->program_id : '';
            $this->level = $supervisor->level ?? '';
            $this->user_id = (string) $supervisor->user_id;
            $this->notes = $supervisor->notes ?? '';
        } else {
            $currentSession = AcademicSession::where('status', 'active')->first();
            $this->academic_session_id = $currentSession ? (string) $currentSession->id : '';
            $this->organization_id = '';
            $this->department_id = '';
            $this->program_id = '';
            $this->level = '';
            $this->user_id = '';
            $this->notes = '';
        }

        $this->showAssignModal = true;
    }

    public function saveSupervisor(AssignPlacementSupervisorAction $action)
    {
        $this->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'organization_id' => 'required|exists:organizations,id',
            'user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'program_id' => 'nullable|exists:programs,id',
            'level' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $action->execute(
            institutionId: Auth::user()->institution_id ?? 1,
            sessionId: (int) $this->academic_session_id,
            organizationId: (int) $this->organization_id,
            userId: (int) $this->user_id,
            assignedBy: Auth::id(),
            departmentId: $this->department_id ? (int) $this->department_id : null,
            programId: $this->program_id ? (int) $this->program_id : null,
            level: $this->level ?: null,
            notes: $this->notes ?: null
        );

        $this->showAssignModal = false;
        session()->flash('success', 'Placement Supervisor assigned successfully.');
    }

    public function deleteSupervisor(int $id)
    {
        $supervisor = PlacementSupervisor::findOrFail($id);
        $supervisor->delete();
        session()->flash('success', 'Supervisor assignment removed.');
    }

    public function render()
    {
        $instId = auth()->user()?->institution_id;

        $supervisors = PlacementSupervisor::query()
            ->with(['academicSession', 'organization', 'department', 'program', 'supervisor', 'assigner', 'supervisable'])
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->when($this->search, function ($q) {
                $q->whereHas('supervisor', fn ($s) => $s->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
                  ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', "%{$this->search}%"));
            })
            ->when($this->sessionFilter, fn ($q) => $q->where('academic_session_id', $this->sessionFilter))
            ->when($this->orgFilter, fn ($q) => $q->where('organization_id', $this->orgFilter))
            ->when($this->deptFilter, fn ($q) => $q->where('department_id', $this->deptFilter))
            ->when($this->programFilter, fn ($q) => $q->where('program_id', $this->programFilter))
            ->when($this->levelFilter, fn ($q) => $q->where('level', $this->levelFilter))
            ->latest()
            ->paginate(12);

        $academicSessions = AcademicSession::orderByDesc('name')->get();

        // Sync any custom organizations from student placements into Organization registry
        $customOrgNames = StudentPlacement::whereNotNull('custom_organization_name')
            ->where('custom_organization_name', '!=', '')
            ->distinct()
            ->pluck('custom_organization_name');

        foreach ($customOrgNames as $cName) {
            Organization::firstOrCreate(
                ['name' => trim($cName)],
                ['category' => 'Host Facility', 'active_status' => true]
            );
        }

        $organizations = Organization::orderBy('name')->get();

        $departments = Department::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->orderBy('name')
            ->get();

        $programs = Program::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->orderBy('name')
            ->get();

        $lecturers = User::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->has('staff')
            ->orderBy('name')
            ->get();

        $totalSupervisors = PlacementSupervisor::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->distinct('user_id')
            ->count('user_id');

        $totalOrganizationsCovered = PlacementSupervisor::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->distinct('organization_id')
            ->count('organization_id');

        return view('livewire.admin.placements.supervisors', compact(
            'supervisors',
            'academicSessions',
            'organizations',
            'departments',
            'programs',
            'lecturers',
            'totalSupervisors',
            'totalOrganizationsCovered'
        ));
    }
}; ?>

<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Placement Supervisors</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Assign & manage supervisors (lecturers) for student placements with multi-tier scoping (Organization, Session, Department, Program, Level).</p>
        </div>
        <div class="flex items-center gap-3">
            <flux:button wire:click="openAssignModal" variant="primary" icon="plus">
                Assign Supervisor
            </flux:button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active Assigned Supervisors</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $totalSupervisors }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Organizations Covered</span>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $totalOrganizationsCovered }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Session Scope</span>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-2">
                {{ $academicSessions->where('status', 'active')->first()?->name ?? 'Default Session' }}
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <flux:input wire:model.live.debounce.300ms="search" label="Search" placeholder="Supervisor or Org..." icon="magnifying-glass" />
            </div>
            <div>
                <flux:select wire:model.live="sessionFilter" label="Session">
                    <flux:select.option value="">All Sessions</flux:select.option>
                    @foreach($academicSessions as $session)
                        <flux:select.option value="{{ $session->id }}">{{ $session->name }} @if($session->status === 'active')(Current)@endif</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="orgFilter" label="Organization">
                    <flux:select.option value="">All Organizations</flux:select.option>
                    @foreach($organizations as $org)
                        <flux:select.option value="{{ $org->id }}">{{ $org->name }}</flux:select.option>
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
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300 text-xs font-semibold uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">Supervisor</th>
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Scope Tier</th>
                        <th class="px-4 py-3">Session</th>
                        <th class="px-4 py-3">Assigned By</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @forelse($supervisors as $sup)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $sup->supervisor?->name ?? 'N/A' }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $sup->supervisor?->email }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium text-indigo-600 dark:text-indigo-400">
                                {{ $sup->organization?->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 space-y-1">
                                <div class="flex flex-wrap gap-1">
                                    @if($sup->department)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                            Dept: {{ $sup->department->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            All Depts
                                        </span>
                                    @endif

                                    @if($sup->program)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                            Prog: {{ $sup->program->code ?? $sup->program->name }}
                                        </span>
                                    @endif

                                    @if($sup->level)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            Level: {{ $sup->level }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $sup->academicSession?->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                {{ $sup->assigner?->name ?? 'System' }}
                            </td>
                            <td class="px-4 py-3 text-right flex items-center justify-end gap-2">
                                <flux:button wire:click="openAssignModal({{ $sup->id }})" variant="ghost" size="sm" icon="pencil">Edit</flux:button>
                                <flux:button wire:click="deleteSupervisor({{ $sup->id }})" wire:confirm="Are you sure you want to remove this supervisor assignment?" variant="danger" size="sm" icon="trash">Remove</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400 text-sm">
                                No supervisor assignments found. Click "Assign Supervisor" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800">
            {{ $supervisors->links() }}
        </div>
    </div>

    <!-- Assignment Modal -->
    @if($showAssignModal)
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ $editingId ? 'Edit Supervisor Assignment' : 'Assign Placement Supervisor' }}
                    </h3>
                    <flux:button wire:click="$set('showAssignModal', false)" variant="ghost" icon="x-mark" size="sm" />
                </div>

                <form wire:submit.prevent="saveSupervisor" class="space-y-4">
                    <div>
                        <flux:select wire:model="user_id" label="Lecturer / Supervisor *">
                            <flux:select.option value="">Select Lecturer...</flux:select.option>
                            @foreach($lecturers as $lec)
                                <flux:select.option value="{{ $lec->id }}">{{ $lec->name }} ({{ $lec->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model="organization_id" label="Organization / Host Company *">
                            <flux:select.option value="">Select Organization...</flux:select.option>
                            @foreach($organizations as $org)
                                <flux:select.option value="{{ $org->id }}">{{ $org->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model="academic_session_id" label="Academic Session (Optional - Default: Active Session)">
                            <flux:select.option value="">All Sessions / Active Session</flux:select.option>
                            @foreach($academicSessions as $sess)
                                <flux:select.option value="{{ $sess->id }}">{{ $sess->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700/60 space-y-3">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block">Target Student Cohort Filter (Optional Scoping)</span>
                        <p class="text-xs text-slate-500">Leave blank to assign supervisor for ALL students in the selected organization.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <flux:select wire:model="department_id" label="Department">
                                    <flux:select.option value="">All Departments</flux:select.option>
                                    @foreach($departments as $d)
                                        <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:select wire:model="program_id" label="Program">
                                    <flux:select.option value="">All Programs</flux:select.option>
                                    @foreach($programs as $p)
                                        <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div>
                                <flux:select wire:model="level" label="Level">
                                    <flux:select.option value="">All Levels</flux:select.option>
                                    <flux:select.option value="100">100 Level</flux:select.option>
                                    <flux:select.option value="200">200 Level</flux:select.option>
                                    <flux:select.option value="300">300 Level</flux:select.option>
                                </flux:select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Notes / Instructions</label>
                        <textarea wire:model="notes" rows="2" placeholder="Optional notes for supervision..." class="w-full text-sm rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"></textarea>
                    </div>

                    <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                        <flux:button type="button" wire:click="$set('showAssignModal', false)" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save Assignment</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
