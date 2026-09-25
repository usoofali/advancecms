<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ProjectSession;
use App\Models\ProjectSessionStage;
use App\Models\AcademicSession;
use App\Models\Staff;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    public ?int $selectedSessionId = null;
    public bool $showSessionModal = false;
    public bool $showStageModal = false;
    public bool $showStagesConfigModal = false;

    // Session Form
    public ?int $editingSessionId = null;
    public string $title = '';
    public ?int $academic_session_id = null;
    public ?int $coordinator_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public string $status = 'Active';
    public ?string $description = null;

    // Stage Form
    public ?int $editingStageId = null;
    public string $stage_title = '';
    public int $stage_order = 1;
    public ?string $stage_description = null;
    public ?string $stage_deadline = null;

    // Delete Confirmation Modal State
    public bool $showDeleteModal = false;
    public string $deleteItemType = ''; // 'session' or 'stage'
    public ?int $deleteItemId = null;
    public string $deleteItemTitle = '';

    public function mount(): void
    {
        $institutionId = auth()->user()->institution_id;
        $activeSession = ProjectSession::where('institution_id', $institutionId)
            ->where('status', 'Active')
            ->first();

        if ($activeSession) {
            $this->selectedSessionId = $activeSession->id;
        } else {
            $first = ProjectSession::where('institution_id', $institutionId)->first();
            $this->selectedSessionId = $first?->id;
        }
    }

    public function selectSession(int $id): void
    {
        $this->selectedSessionId = $id;
    }

    public function configureStages(ProjectSession $session): void
    {
        $this->selectedSessionId = $session->id;
        $this->showStagesConfigModal = true;
    }

    public function openNewSessionModal(): void
    {
        $this->editingSessionId = null;
        $this->title = '';
        $this->academic_session_id = AcademicSession::where('status', 'active')->first()?->id ?? AcademicSession::first()?->id;
        $this->coordinator_id = null;
        $this->start_date = now()->format('Y-m-d');
        $this->end_date = now()->addMonths(6)->format('Y-m-d');
        $this->status = 'Active';
        $this->description = '';
        $this->showSessionModal = true;
    }

    public function editSession(ProjectSession $session): void
    {
        $this->editingSessionId = $session->id;
        $this->title = $session->title;
        $this->academic_session_id = $session->academic_session_id;
        $this->coordinator_id = $session->coordinator_id;
        $this->start_date = $session->start_date?->format('Y-m-d');
        $this->end_date = $session->end_date?->format('Y-m-d');
        $this->status = $session->status;
        $this->description = $session->description;
        $this->showSessionModal = true;
    }

    public function saveSession(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'coordinator_id' => 'nullable|exists:staff,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:Active,Archived',
            'description' => 'nullable|string',
        ]);

        $institutionId = auth()->user()->institution_id;

        $session = ProjectSession::updateOrCreate(
            ['id' => $this->editingSessionId],
            [
                'institution_id' => $institutionId,
                'academic_session_id' => $this->academic_session_id,
                'title' => $this->title,
                'coordinator_id' => $this->coordinator_id,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'status' => $this->status,
                'description' => $this->description,
            ]
        );

        // If new session, seed default stages
        if (! $this->editingSessionId) {
            $defaultStages = [
                ['title' => 'Project Proposal', 'order' => 1, 'description' => 'Initial proposal & problem statement approval'],
                ['title' => 'Chapter One', 'order' => 2, 'description' => 'Introduction, background of study, objectives, and research questions'],
                ['title' => 'Chapter Two', 'order' => 3, 'description' => 'Literature review and theoretical framework'],
                ['title' => 'Chapter Three', 'order' => 4, 'description' => 'Research methodology and design'],
                ['title' => 'Chapter Four', 'order' => 5, 'description' => 'Data presentation, analysis, and discussion of findings'],
                ['title' => 'Chapter Five', 'order' => 6, 'description' => 'Summary, conclusion, and recommendations'],
                ['title' => 'Final Project Submission', 'order' => 7, 'description' => 'Full bound project document for final approval'],
            ];

            foreach ($defaultStages as $stage) {
                ProjectSessionStage::create([
                    'project_session_id' => $session->id,
                    'title' => $stage['title'],
                    'stage_order' => $stage['order'],
                    'description' => $stage['description'],
                ]);
            }
        }

        $this->selectedSessionId = $session->id;
        $this->showSessionModal = false;
        Flux::toast('Project session saved successfully.', variant: 'success');
    }

    public function openNewStageModal(): void
    {
        if (! $this->selectedSessionId) {
            Flux::toast('Please select or create a project session first.', variant: 'warning');
            return;
        }

        $session = ProjectSession::find($this->selectedSessionId);
        $nextOrder = ($session->stages()->max('stage_order') ?? 0) + 1;

        $this->editingStageId = null;
        $this->stage_title = '';
        $this->stage_order = $nextOrder;
        $this->stage_description = '';
        $this->stage_deadline = null;
        $this->showStageModal = true;
    }

    public function editStage(ProjectSessionStage $stage): void
    {
        $this->editingStageId = $stage->id;
        $this->stage_title = $stage->title;
        $this->stage_order = $stage->stage_order;
        $this->stage_description = $stage->description;
        $this->stage_deadline = $stage->deadline?->format('Y-m-d');
        $this->showStageModal = true;
    }

    public function saveStage(): void
    {
        $this->validate([
            'stage_title' => 'required|string|max:255',
            'stage_order' => 'required|integer|min:1',
            'stage_description' => 'nullable|string',
            'stage_deadline' => 'nullable|date',
        ]);

        ProjectSessionStage::updateOrCreate(
            ['id' => $this->editingStageId],
            [
                'project_session_id' => $this->selectedSessionId,
                'title' => $this->stage_title,
                'stage_order' => $this->stage_order,
                'description' => $this->stage_description,
                'deadline' => $this->stage_deadline,
            ]
        );

        $this->showStageModal = false;
        Flux::toast('Project stage saved successfully.', variant: 'success');
    }

    public function confirmDeleteSession(ProjectSession $session): void
    {
        $this->deleteItemType = 'session';
        $this->deleteItemId = $session->id;
        $this->deleteItemTitle = $session->title;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteStage(ProjectSessionStage $stage): void
    {
        $this->deleteItemType = 'stage';
        $this->deleteItemId = $stage->id;
        $this->deleteItemTitle = $stage->title;
        $this->showDeleteModal = true;
    }

    public function deleteConfirmed(): void
    {
        if (! $this->deleteItemId) return;

        if ($this->deleteItemType === 'session') {
            ProjectSession::destroy($this->deleteItemId);
            if ($this->selectedSessionId === $this->deleteItemId) {
                $this->selectedSessionId = ProjectSession::where('institution_id', auth()->user()->institution_id)->first()?->id;
            }
            Flux::toast('Project session deleted successfully.', variant: 'success');
        } elseif ($this->deleteItemType === 'stage') {
            ProjectSessionStage::destroy($this->deleteItemId);
            Flux::toast('Workflow stage removed successfully.', variant: 'success');
        }

        $this->showDeleteModal = false;
        $this->deleteItemId = null;
        $this->deleteItemTitle = '';
    }

    public function with(): array
    {
        $institutionId = auth()->user()->institution_id;
        $sessions = ProjectSession::with(['academicSession', 'coordinator', 'stages'])
            ->where('institution_id', $institutionId)
            ->latest()
            ->get();

        $selectedSession = $this->selectedSessionId
            ? ProjectSession::with(['stages', 'coordinator', 'academicSession'])->find($this->selectedSessionId)
            : null;

        $academicSessions = AcademicSession::orderBy('name', 'desc')->get();
        $staffMembers = Staff::where('institution_id', $institutionId)->get();

        return compact('sessions', 'selectedSession', 'academicSessions', 'staffMembers');
    }
};
?>

<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Academic Project Sessions & Workflow</h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Manage session cycles, coordinators, deadlines, and chapter supervision stages.</p>
        </div>
        <div class="w-full sm:w-auto">
            <flux:button wire:click="openNewSessionModal" variant="primary" icon="plus" class="w-full sm:w-auto">
                New Project Session
            </flux:button>
        </div>
    </div>

    <!-- Project Sessions Full-Width Table View -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-xs mb-6">
        <flux:table class="[&_td:first-child]:!ps-6 [&_td:last-child]:!pe-6 [&_th:first-child]:!ps-6 [&_th:last-child]:!pe-6 [&_td]:!px-6 [&_th]:!px-6 [&_td]:!py-4 [&_th]:!py-3.5">
            <flux:table.columns>
                <flux:table.column>Session & Academic Year</flux:table.column>
                <flux:table.column>Coordinator Staff</flux:table.column>
                <flux:table.column>Workflow Stages</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($sessions as $sess)
                    <flux:table.row :key="$sess->id" class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/50">
                        <flux:table.cell>
                            <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $sess->title }}</div>
                            <div class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5">
                                {{ $sess->academicSession?->name ?? 'N/A' }} Academic Session
                            </div>
                            @if($sess->description)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ $sess->description }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                {{ $sess->coordinator ? $sess->coordinator->first_name . ' ' . $sess->coordinator->last_name : 'Unassigned' }}
                            </div>
                            @if($sess->coordinator)
                                <div class="text-xs text-gray-500 font-mono">{{ $sess->coordinator->staff_number }}</div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                <flux:icon.adjustments-horizontal class="w-3.5 h-3.5" />
                                {{ $sess->stages->count() }} Stages
                            </span>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :variant="$sess->status === 'Active' ? 'success' : 'subtle'">
                                {{ $sess->status }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="subtle" icon="ellipsis-horizontal" size="sm" class="cursor-pointer" aria-label="Session actions" />
                                <flux:menu>
                                    <flux:menu.item wire:click="configureStages({{ $sess->id }})" icon="cog-6-tooth">
                                        Configure Stages
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="editSession({{ $sess->id }})" icon="pencil">
                                        Edit Session
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="confirmDeleteSession({{ $sess->id }})" variant="danger" icon="trash">
                                        Delete Session
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-10">
                            <flux:icon.calendar-date-range class="w-10 h-10 mx-auto text-gray-400 mb-2" />
                            <p class="text-sm font-medium text-gray-500">No project sessions configured yet.</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Workflow Stages Configuration Modal -->
    <flux:modal wire:model="showStagesConfigModal" class="w-full max-w-full sm:max-w-3xl">
        @if($selectedSession)
            <div class="space-y-6">
                <!-- Modal Header (Padded to avoid close button overlap) -->
                <div class="pr-10 border-b pb-4 border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $selectedSession->title }}</h3>
                        <flux:badge :variant="$selectedSession->status === 'Active' ? 'success' : 'subtle'">
                            {{ $selectedSession->status }}
                        </flux:badge>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Academic Session: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $selectedSession->academicSession?->name ?? 'N/A' }}</span>
                        @if($selectedSession->coordinator)
                            | Coordinator: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $selectedSession->coordinator->first_name }} {{ $selectedSession->coordinator->last_name }}</span>
                        @endif
                    </p>
                </div>

                @if($selectedSession->description)
                    <div class="p-3.5 bg-gray-50 dark:bg-zinc-800/50 rounded-xl text-xs text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-800">
                        <span class="font-bold text-gray-900 dark:text-white">Guidelines:</span> {{ $selectedSession->description }}
                    </div>
                @endif

                <!-- Stages Section Header & Add Stage Button -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white">Configured Stage Sequence</h4>
                        <flux:button wire:click="openNewStageModal" variant="primary" icon="plus" size="sm">
                            Add New Stage
                        </flux:button>
                    </div>

                    <!-- Single Clean Container without nested double scrollbars -->
                    <div class="space-y-3">
                        @forelse($selectedSession->stages as $stg)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-zinc-900 gap-4 shadow-xs">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 shrink-0 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 flex items-center justify-center font-bold text-sm border border-indigo-200 dark:border-indigo-800">
                                        {{ $stg->stage_order }}
                                    </div>
                                    <div>
                                        <h5 class="font-bold text-gray-900 dark:text-white text-base">{{ $stg->title }}</h5>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $stg->description ?: 'No detailed scope set for this stage.' }}</p>
                                        @if($stg->deadline)
                                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 font-semibold flex items-center gap-1">
                                                <flux:icon.clock class="w-3.5 h-3.5 inline" /> Deadline: {{ $stg->deadline->format('M d, Y') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 justify-end shrink-0">
                                    <flux:button wire:click="editStage({{ $stg->id }})" variant="subtle" icon="pencil" size="sm">
                                        Edit
                                    </flux:button>
                                    <flux:button wire:click="confirmDeleteStage({{ $stg->id }})" variant="danger" icon="trash" size="sm">
                                        Delete
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center border border-dashed rounded-xl border-gray-300 dark:border-gray-700">
                                <p class="text-sm text-gray-500 italic">No workflow stages configured yet for this session.</p>
                                <flux:button wire:click="openNewStageModal" variant="subtle" size="sm" class="mt-3">
                                    Add First Stage
                                </flux:button>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-800">
                    <flux:button wire:click="$set('showStagesConfigModal', false)" variant="subtle">Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Session Modal -->
    <flux:modal wire:model="showSessionModal" class="w-full max-w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingSessionId ? 'Edit Project Session' : 'Create Project Session' }}</h3>
                <p class="text-xs text-gray-500">Configure project calendar session and supervisor coordinator.</p>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="title" label="Session Title" placeholder="e.g. 2026/2027 Project Session" />
                
                <flux:select wire:model="academic_session_id" label="Academic Session" class="!text-gray-900 dark:!text-white font-semibold">
                    <option value="">Select Academic Session</option>
                    @foreach($academicSessions as $acSess)
                        <option value="{{ $acSess->id }}">{{ $acSess->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="coordinator_id" label="Project Coordinator (Staff)" class="!text-gray-900 dark:!text-white font-semibold">
                    <option value="">Select Coordinator Staff</option>
                    @foreach($staffMembers as $stf)
                        <option value="{{ $stf->id }}">{{ $stf->first_name }} {{ $stf->last_name }} ({{ $stf->staff_number }})</option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="start_date" label="Start Date" />
                    <flux:input type="date" wire:model="end_date" label="End Date" />
                </div>

                <flux:select wire:model="status" label="Session Status" class="!text-gray-900 dark:!text-white font-semibold">
                    <option value="Active">Active</option>
                    <option value="Archived">Archived</option>
                </flux:select>

                <flux:textarea wire:model="description" label="Description / Guidelines" rows="3" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <flux:button wire:click="$set('showSessionModal', false)" variant="subtle">Cancel</flux:button>
                <flux:button wire:click="saveSession" variant="primary">Save Session</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Stage Modal -->
    <flux:modal wire:model="showStageModal" class="w-full max-w-full sm:max-w-md">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $editingStageId ? 'Edit Stage' : 'Add Workflow Stage' }}</h3>
                <p class="text-xs text-gray-500">Set stage title, sequence order, and submission deadline.</p>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="stage_title" label="Stage Title" placeholder="e.g. Chapter One" />
                <flux:input type="number" wire:model="stage_order" label="Stage Sequence Order" min="1" />
                <flux:input type="date" wire:model="stage_deadline" label="Submission Deadline (Optional)" />
                <flux:textarea wire:model="stage_description" label="Stage Instructions / Scope" rows="3" />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <flux:button wire:click="$set('showStageModal', false)" variant="subtle">Cancel</flux:button>
                <flux:button wire:click="saveStage" variant="primary">Save Stage</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Confirmation Modal for Delete Operations -->
    <flux:modal wire:model="showDeleteModal" class="w-full max-w-full sm:max-w-md">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                    <flux:icon.exclamation-triangle class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirm Deletion</h3>
                    <p class="text-xs text-gray-500">This action is permanent and cannot be undone.</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Are you sure you want to delete the {{ $deleteItemType }} <span class="font-bold text-gray-900 dark:text-white">"{{ $deleteItemTitle }}"</span>? All associated details will be removed.
            </p>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                <flux:button wire:click="$set('showDeleteModal', false)" variant="subtle">Cancel</flux:button>
                <flux:button wire:click="deleteConfirmed" variant="danger">Confirm Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
