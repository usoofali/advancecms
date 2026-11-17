<?php

use App\Actions\Placements\ApprovePlacementRequestAction;
use App\Actions\Placements\VerifyPlacementAcceptanceAction;
use App\Enums\PlacementApprovalStatus;
use App\Enums\PlacementStatus;
use App\Enums\PlacementWorkflowStage;
use App\Models\AcademicSession;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\PlacementType;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Services\DocumentGenerationService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $approvalFilter = '';

    public $workflowStageFilter = '';

    public $programFilter = '';

    public $levelFilter = '';

    public $organizationFilter = '';

    public $showModal = false;

    public $generateModal = false;

    public $batchModal = false;

    // Form fields for new placement
    public $create_program_id = '';

    public $create_level = '';

    public $student_id = '';

    public $organization_id = '';

    public $placement_type_id = '';

    public $start_date = '';

    public $end_date = '';

    public $academic_session = '';

    public $single_generate_letter = false;

    public $single_template_id = '';

    public $create_place_mode = 'directory';

    public $create_custom_name = '';

    public $create_custom_address = '';

    public $create_custom_city = '';

    public $create_custom_state = '';

    public $assignPlaceModal = false;

    public ?StudentPlacement $placementToAssign = null;

    public $assign_place_mode = 'directory';

    public $assign_organization_id = '';

    public $assign_custom_name = '';

    public $assign_custom_address = '';

    public $assign_custom_city = '';

    public $assign_custom_state = '';

    // Batch Generation fields
    public $batch_session = '';

    public $batch_program_id = '';

    public $batch_level = '';

    public $batch_organization_id = '';

    public $batch_placement_type_id = '';

    public $batch_start_date = '';

    public $batch_end_date = '';

    public $batch_template_id = '';

    public $batch_generate_letters = false;

    public array $batch_selected_student_ids = [];

    public bool $batch_select_all = true;

    public string $batch_student_search = '';

    // Generation fields
    public ?StudentPlacement $placementToGenerate = null;

    public $selectedTemplateId = '';

    public $rejectModal = false;

    public ?StudentPlacement $placementToReject = null;

    public $rejection_reason = '';

    public $rejection_type = 'organization'; // 'organization' or 'acceptance'

    public $cancelModal = false;

    public $placementToCancelId = null;

    public $selectedPlacements = [];

    public $selectAll = false;

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedPlacements = $this->with()['placements']->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedPlacements = [];
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingProgramFilter()
    {
        $this->resetPage();
    }

    public function updatingLevelFilter()
    {
        $this->resetPage();
    }

    public function updatingOrganizationFilter()
    {
        $this->resetPage();
    }

    public function updatingWorkflowStageFilter()
    {
        $this->resetPage();
    }

    public function updatingApprovalFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    protected function findScopedPlacement(int|string $placementId): ?StudentPlacement
    {
        $instId = auth()->user()?->institution_id;

        return StudentPlacement::query()
            ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
            ->find($placementId);
    }

    public function rules()
    {
        return [
            'student_id' => 'required|exists:students,id',
            'placement_type_id' => 'required|exists:placement_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'academic_session' => 'required|string',
        ];
    }

    public function createPlacement()
    {
        $this->reset([
            'create_program_id',
            'create_level',
            'student_id',
            'organization_id',
            'placement_type_id',
            'start_date',
            'end_date',
            'academic_session',
            'single_generate_letter',
            'single_template_id',
            'create_place_mode',
            'create_custom_name',
            'create_custom_address',
            'create_custom_city',
            'create_custom_state',
        ]);
        $this->academic_session = AcademicSession::where('status', 'active')->value('name') ?? '';
        $this->showModal = true;
    }

    public function openAssignPlaceModal($placementId)
    {
        $this->placementToAssign = $this->findScopedPlacement($placementId);
        if (!$this->placementToAssign) {
            return;
        }

        $this->assign_place_mode = $this->placementToAssign->organization_id ? 'directory' : ($this->placementToAssign->custom_organization_name ? 'custom' : 'directory');
        $this->assign_organization_id = $this->placementToAssign->organization_id ?? '';
        $this->assign_custom_name = $this->placementToAssign->custom_organization_name ?? '';
        $this->assign_custom_address = $this->placementToAssign->custom_organization_address ?? '';
        $this->assign_custom_city = $this->placementToAssign->custom_organization_city ?? '';
        $this->assign_custom_state = $this->placementToAssign->custom_organization_state ?? '';

        $this->assignPlaceModal = true;
    }

    public function saveAssignedPlace()
    {
        if (!$this->placementToAssign) {
            return;
        }

        if ($this->assign_place_mode === 'directory') {
            $this->validate(['assign_organization_id' => 'required|exists:organizations,id'], [
                'assign_organization_id.required' => 'Please select an organization from the directory.',
            ]);

            $this->placementToAssign->update([
                'organization_id' => $this->assign_organization_id,
                'custom_organization_name' => null,
                'custom_organization_address' => null,
                'custom_organization_city' => null,
                'custom_organization_state' => null,
            ]);
        } else {
            $this->validate([
                'assign_custom_name' => 'required|string|max:255',
                'assign_custom_address' => 'required|string|max:500',
                'assign_custom_city' => 'nullable|string|max:100',
                'assign_custom_state' => 'nullable|string|max:100',
            ]);

            $this->placementToAssign->update([
                'organization_id' => null,
                'custom_organization_name' => $this->assign_custom_name,
                'custom_organization_address' => $this->assign_custom_address,
                'custom_organization_city' => $this->assign_custom_city,
                'custom_organization_state' => $this->assign_custom_state,
            ]);
        }

        if (in_array($this->placementToAssign->workflow_stage, ['Pending_Selection', 'Pending_Request_Approval', null])) {
            $this->placementToAssign->update([
                'workflow_stage' => PlacementWorkflowStage::REQUEST_APPROVED->value,
                'status' => PlacementStatus::ASSIGNED->value,
            ]);
        }

        Flux::toast('Place of posting assigned successfully!', variant: 'success');
        $this->assignPlaceModal = false;
    }

    public function openBatchModal()
    {
        $this->reset([
            'batch_session',
            'batch_program_id',
            'batch_level',
            'batch_organization_id',
            'batch_placement_type_id',
            'batch_start_date',
            'batch_end_date',
            'batch_template_id',
            'batch_generate_letters',
            'batch_selected_student_ids',
            'batch_student_search',
        ]);
        $this->batch_select_all = true;
        $this->batch_session = AcademicSession::where('status', 'active')->value('name') ?? '';
        $this->batchModal = true;
    }

    public function updatedBatchSession(): void
    {
        $this->syncBatchSelection();
    }

    public function updatedBatchProgramId(): void
    {
        $this->syncBatchSelection();
    }

    public function updatedBatchLevel(): void
    {
        $this->syncBatchSelection();
    }

    public function updatedBatchSelectAll(bool $value): void
    {
        if ($value) {
            $this->batch_selected_student_ids = $this->getBatchMatchingStudents()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->batch_selected_student_ids = [];
        }
    }

    protected function syncBatchSelection(): void
    {
        $students = $this->getBatchMatchingStudents();
        if ($this->batch_select_all) {
            $this->batch_selected_student_ids = $students
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->batch_selected_student_ids = [];
        }
    }

    protected function getBatchMatchingStudents()
    {
        if (! $this->batch_session || ! $this->batch_program_id || ! $this->batch_level) {
            return collect();
        }

        $session = AcademicSession::where('name', $this->batch_session)->first();
        if (! $session) {
            return collect();
        }

        $instId = auth()->user()?->institution_id;

        return Student::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->where('program_id', $this->batch_program_id)
            ->atLevel($this->batch_level, $session)
            ->orderBy('first_name')
            ->get();
    }

    public function savePlacement(DocumentGenerationService $generator)
    {
        $validated = $this->validate();

        if ($this->create_place_mode === 'directory') {
            $this->validate(['organization_id' => 'required|exists:organizations,id']);
            $validated['organization_id'] = $this->organization_id;
        } else {
            $this->validate([
                'create_custom_name' => 'required|string|max:255',
                'create_custom_address' => 'required|string|max:500',
                'create_custom_city' => 'nullable|string|max:100',
                'create_custom_state' => 'nullable|string|max:100',
            ]);
            $validated['organization_id'] = null;
            $validated['custom_organization_name'] = $this->create_custom_name;
            $validated['custom_organization_address'] = $this->create_custom_address;
            $validated['custom_organization_city'] = $this->create_custom_city;
            $validated['custom_organization_state'] = $this->create_custom_state;
        }

        if ($this->single_generate_letter && !$this->single_template_id) {
            Flux::toast('Please select a template to generate the letter.', variant: 'danger');

            return;
        }

        // Check for overlapping placements
        if (StudentPlacement::hasOverlap($validated['student_id'], $validated['start_date'], $validated['end_date'])) {
            Flux::toast('Student already has an overlapping placement for these dates.', variant: 'danger');

            return;
        }

        $validated['assigned_by'] = auth()->id();
        $validated['assigned_at'] = now();
        $validated['status'] = 'Assigned';
        $validated['workflow_stage'] = 'Request_Approved';
        $validated['approval_status'] = $this->single_generate_letter ? 'Generated' : 'Draft';

        try {
            DB::beginTransaction();

            $placement = StudentPlacement::create($validated);

            if ($this->single_generate_letter) {
                $template = DocumentTemplate::find($this->single_template_id);
                if ($template) {
                    $generator->generateRecord($placement, $template, 'request');
                }
            }

            DB::commit();
            Flux::toast('Placement created successfully.', variant: 'success');
            $this->showModal = false;
        } catch (Exception $e) {
            DB::rollBack();
            Flux::toast('Error: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function processBatchGeneration(DocumentGenerationService $generator)
    {
        $this->validate([
            'batch_session' => 'required|string',
            'batch_program_id' => 'required|exists:programs,id',
            'batch_level' => 'required|integer',
            'batch_organization_id' => 'nullable|exists:organizations,id',
            'batch_placement_type_id' => 'required|exists:placement_types,id',
            'batch_start_date' => 'required|date',
            'batch_end_date' => 'required|date|after_or_equal:batch_start_date',
            'batch_selected_student_ids' => 'required|array|min:1',
        ], [
            'batch_selected_student_ids.required' => 'Please select at least one student to assign.',
            'batch_selected_student_ids.min' => 'Please select at least one student to assign.',
        ]);

        if ($this->batch_generate_letters && !$this->batch_template_id) {
            Flux::toast('Please select a template to generate letters.', variant: 'danger');

            return;
        }

        $instId = auth()->user()?->institution_id;
        $students = Student::query()
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->whereIn('id', $this->batch_selected_student_ids)
            ->get();

        if ($students->isEmpty()) {
            Flux::toast('No students found matching your selection.', variant: 'warning');

            return;
        }

        $template = $this->batch_generate_letters ? DocumentTemplate::find($this->batch_template_id) : null;
        $successCount = 0;
        $skipCount = 0;

        try {
            DB::beginTransaction();

            foreach ($students as $student) {
                if (StudentPlacement::hasOverlap($student->id, $this->batch_start_date, $this->batch_end_date)) {
                    $skipCount++;

                    continue;
                }

                $stage = empty($this->batch_organization_id) ? 'Pending_Selection' : 'Pending_Request_Approval';

                $placement = StudentPlacement::create([
                    'student_id' => $student->id,
                    'organization_id' => $this->batch_organization_id ?: null,
                    'placement_type_id' => $this->batch_placement_type_id,
                    'start_date' => $this->batch_start_date,
                    'end_date' => $this->batch_end_date,
                    'academic_session' => $this->batch_session,
                    'status' => PlacementStatus::ASSIGNED->value,
                    'workflow_stage' => $stage,
                    'approval_status' => $this->batch_generate_letters ? PlacementApprovalStatus::GENERATED->value : PlacementApprovalStatus::DRAFT->value,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                ]);

                if ($this->batch_generate_letters && $template) {
                    $generator->generateRecord($placement, $template, 'request');
                }

                $successCount++;
            }

            DB::commit();

            Flux::toast("Bulk assignment complete: {$successCount} assigned, {$skipCount} skipped due to overlap.", variant: 'success');
            $this->batchModal = false;

        } catch (Exception $e) {
            DB::rollBack();
            Flux::toast('Error during batch processing: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function approveRequest($placementId, ApprovePlacementRequestAction $action)
    {
        $placement = $this->findScopedPlacement($placementId);
        if (!$placement) {
            return;
        }

        $action->execute($placement);

        Flux::toast('Request Approved and letters generated.', variant: 'success');
    }

    public function bulkApproveRequests(ApprovePlacementRequestAction $action)
    {
        if (empty($this->selectedPlacements)) {
            return;
        }

        $instId = auth()->user()?->institution_id;
        $placements = StudentPlacement::query()
            ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
            ->whereIn('id', $this->selectedPlacements)
            ->get();
        $count = 0;
        foreach ($placements as $placement) {
            if ($placement->workflow_stage === PlacementWorkflowStage::PENDING_REQUEST_APPROVAL->value) {
                $action->execute($placement);
                $count++;
            }
        }

        $this->selectedPlacements = [];
        Flux::toast("$count selected request(s) approved.", variant: 'success');
    }

    public function verifyAcceptanceAndPost($placementId, VerifyPlacementAcceptanceAction $action)
    {
        $placement = $this->findScopedPlacement($placementId);
        if (!$placement) {
            return;
        }

        $action->execute($placement);

        Flux::toast('Acceptance verified! Final Posting Authorization issued.', variant: 'success');
    }

    public function openRejectModal($placementId, $rejectionType = 'organization')
    {
        $this->placementToReject = $this->findScopedPlacement($placementId);
        if (!$this->placementToReject) {
            return;
        }

        $this->rejection_type = $rejectionType;
        $this->rejection_reason = $this->placementToReject->admin_remarks ?? '';
        $this->rejectModal = true;
    }

    public function processRejection()
    {
        $this->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [
            'rejection_reason.required' => 'Please provide a reason or remarks so the student understands why this action occurred.',
        ]);

        if (!$this->placementToReject) {
            return;
        }

        if ($this->rejection_type === 'organization') {
            $this->placementToReject->update([
                'workflow_stage' => 'Pending_Selection',
                'status' => 'Pending',
                'organization_id' => null,
                'custom_organization_name' => null,
                'custom_organization_address' => null,
                'custom_organization_city' => null,
                'custom_organization_state' => null,
                'admin_remarks' => $this->rejection_reason,
            ]);
            Flux::toast('Organization request rejected. Student must re-select.', variant: 'success');
        } elseif ($this->rejection_type === 'acceptance') {
            $this->placementToReject->update([
                'workflow_stage' => PlacementWorkflowStage::REQUEST_APPROVED->value,
                'status' => PlacementStatus::ASSIGNED->value,
                'admin_remarks' => $this->rejection_reason,
            ]);
            $this->placementToReject->placementDocuments()->delete();
            Flux::toast('Acceptance scan rejected. Student prompted to re-upload.', variant: 'success');
        }

        $this->rejectModal = false;
    }

    public function confirmCancelPlacement($placementId)
    {
        $this->placementToCancelId = $placementId;
        $this->cancelModal = true;
    }

    public function cancelPlacement($placementId = null)
    {
        $id = $placementId ?: $this->placementToCancelId;
        if (!$id) {
            return;
        }

        $placement = $this->findScopedPlacement($id);
        if (!$placement) {
            return;
        }

        $placement->update([
            'status' => 'Cancelled',
            'admin_remarks' => 'Placement cancelled by Administrator.',
        ]);
        Flux::toast('Placement status changed to Cancelled.', variant: 'warning');

        $this->cancelModal = false;
        $this->placementToCancelId = null;
    }

    public function restorePlacement($placementId)
    {
        $placement = $this->findScopedPlacement($placementId);
        if (!$placement) {
            return;
        }

        $placement->update([
            'status' => 'Assigned',
            'admin_remarks' => null,
        ]);
        Flux::toast('Placement reinstated successfully.', variant: 'success');
    }

    public function generateGroupCoverLetterForOrganization($organizationId)
    {
        $generator = app(DocumentGenerationService::class);
        $instId = auth()->user()?->institution_id;

        $placements = StudentPlacement::with(['student.program'])
            ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
            ->where('organization_id', $organizationId)
            ->whereIn('workflow_stage', ['Request_Approved', 'Acceptance_Submitted', 'Posting_Issued'])
            ->get();

        if ($placements->isEmpty()) {
            Flux::toast('No approved placements found for this organization.', variant: 'warning');

            return;
        }

        $batchGroupId = 'GRP_' . strtoupper(substr(uniqid(), -6));
        $template = DocumentTemplate::where('type', 'Group Cover')->where('active', true)->first();

        if (!$template) {
            Flux::toast('Group Cover template not configured.', variant: 'danger');

            return;
        }

        DB::beginTransaction();
        try {
            foreach ($placements as $placement) {
                $generator->generateRecord($placement, $template, 'group_cover', $batchGroupId);
            }
            DB::commit();

            $firstDoc = $placements->first()->generatedDocuments()->where('purpose', 'group_cover')->first();
            Flux::toast('Consolidated Group Cover Letter generated successfully!', variant: 'success');

            if ($firstDoc) {
                return redirect()->route('cms.placements.print', ['doc' => $firstDoc->document_number]);
            }
        } catch (Throwable $e) {
            DB::rollBack();
            Flux::toast('Error generating group cover: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function updateApproval(StudentPlacement $placement, $newStatus)
    {
        $placement->update(['approval_status' => $newStatus]);
        Flux::toast("Placement moved to {$newStatus}.", variant: 'success');
    }

    public function openGenerateModal($placementId)
    {
        $this->placementToGenerate = $this->findScopedPlacement($placementId);
        if (!$this->placementToGenerate) {
            return;
        }
        $this->selectedTemplateId = '';
        $this->generateModal = true;
    }

    public function generateLetter()
    {
        $generator = app(DocumentGenerationService::class);

        $this->validate([
            'selectedTemplateId' => 'required|exists:document_templates,id',
        ]);

        if (!$this->placementToGenerate) {
            return;
        }

        $template = DocumentTemplate::find($this->selectedTemplateId);

        try {
            DB::beginTransaction();

            $generator->generateRecord($this->placementToGenerate, $template, 'request');
            $this->placementToGenerate->update(['approval_status' => 'Generated']);

            DB::commit();

            Flux::toast('Letter generated successfully!', variant: 'success');
            $this->generateModal = false;

        } catch (Throwable $e) {
            DB::rollBack();
            Flux::toast('Error generating letter: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function with(): array
    {
        $instId = auth()->user()?->institution_id;
        $activeSession = AcademicSession::where('status', 'active')->first();

        return [
            'placements' => StudentPlacement::query()
                ->with([
                    'student',
                    'organization',
                    'placementType',
                    'placementDocuments',
                    'generatedDocuments' => fn ($q) => $q->latest(),
                ])
                ->when($instId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('institution_id', $instId)))
                ->when($this->search, function ($query) {
                    $query->whereHas('student', function ($q) {
                        $q->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%')
                            ->orWhere('matric_number', 'like', '%' . $this->search . '%');
                    });
                })
                ->when($this->programFilter, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('program_id', $this->programFilter)))
                ->when($this->levelFilter, fn($q) => $q->filterByLevel((int) $this->levelFilter, $activeSession))
                ->when($this->organizationFilter, function ($q) {
                    if ($this->organizationFilter === 'custom') {
                        $q->whereNull('organization_id')->whereNotNull('custom_organization_name');
                    } else {
                        $q->where('organization_id', $this->organizationFilter);
                    }
                })
                ->when($this->workflowStageFilter, fn($q) => $q->where('workflow_stage', $this->workflowStageFilter))
                ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
                ->when($this->approvalFilter, fn($q) => $q->where('approval_status', $this->approvalFilter))
                ->orderBy('created_at', 'desc')
                ->paginate(10),

            'modalStudents' => ($this->create_program_id && $this->create_level && $activeSession)
                ? Student::query()
                    ->when($instId, fn ($q) => $q->where('institution_id', $instId))
                    ->where('program_id', $this->create_program_id)
                    ->atLevel($this->create_level, $activeSession)
                    ->orderBy('first_name')
                    ->get()
                : collect(),
            'organizations' => Organization::where('active_status', true)->get(),
            'types' => PlacementType::query()
                ->where('is_active', true)
                ->get(),
            'templates' => DocumentTemplate::where('active', true)->get(),
            'programs' => Program::query()
                ->when($instId, fn ($q) => $q->where('institution_id', $instId))
                ->orderBy('name')
                ->get(),
            'academicSessions' => AcademicSession::orderBy('name', 'desc')->get(),
            'batchStudents' => $this->getBatchMatchingStudents(),
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Manage Placements</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assign students to host organizations, review posting requests, and issue placement letters.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button wire:click="openBatchModal" variant="filled" icon="user-group" class="w-full sm:w-auto">
                Bulk Assign</flux:button>
            <flux:button wire:click="createPlacement" variant="primary" icon="plus" class="w-full sm:w-auto">New
                Assignment</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="Search student..." />

            <flux:select wire:model.live="programFilter" placeholder="All Programs">
                <flux:select.option value="">All Programs</flux:select.option>
                @foreach($programs as $prog)
                    <flux:select.option value="{{ $prog->id }}">{{ $prog->acronym ?? $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="levelFilter" placeholder="All Levels">
                <flux:select.option value="">All Levels</flux:select.option>
                @foreach([100, 200, 300, 400, 500] as $lvl)
                    <flux:select.option value="{{ $lvl }}">{{ $lvl }}L</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="organizationFilter" placeholder="All Organizations">
                <flux:select.option value="">All Organizations</flux:select.option>
                <flux:select.option value="custom">Custom Organizations</flux:select.option>
                @foreach($organizations as $org)
                    <flux:select.option value="{{ $org->id }}">{{ str($org->name)->limit(24) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="workflowStageFilter" placeholder="All Statuses">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="Pending_Selection">Awaiting Selection</flux:select.option>
                <flux:select.option value="Pending_Request_Approval">Pending Approval</flux:select.option>
                <flux:select.option value="Request_Approved">Approved</flux:select.option>
                <flux:select.option value="Acceptance_Submitted">Acceptance Uploaded</flux:select.option>
                <flux:select.option value="Posting_Issued">Posting Issued</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="All Record States">
                <flux:select.option value="">All States</flux:select.option>
                @foreach(\App\Enums\PlacementStatus::cases() as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->value }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="approvalFilter" placeholder="All Approvals">
                <flux:select.option value="">All Approvals</flux:select.option>
                <flux:select.option value="Draft">Draft</flux:select.option>
                <flux:select.option value="Department_Approved">Dept. Approved</flux:select.option>
                <flux:select.option value="Academic_Approved">Academic Approved</flux:select.option>
                <flux:select.option value="Generated">Generated</flux:select.option>
            </flux:select>
        </div>

        <div class="relative overflow-x-auto">
            @if(count($selectedPlacements) > 0)
                <div
                    class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-blue-200 dark:border-blue-800">
                    <span class="text-sm text-blue-800 dark:text-blue-300">
                        <strong>{{ count($selectedPlacements) }}</strong> placement(s) selected
                    </span>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <flux:button wire:click="bulkApproveRequests" size="sm" variant="primary" icon="check" class="flex-1 sm:flex-initial">
                            Approve Requests
                        </flux:button>
                        <flux:button wire:click="$set('selectedPlacements', [])" size="sm" variant="ghost" class="flex-1 sm:flex-initial">
                            Clear Selection
                        </flux:button>
                    </div>
                </div>
            @endif

            <flux:table :paginate="$placements">
                <flux:table.columns>
                    <flux:table.column>
                        <flux:checkbox wire:model.live="selectAll" />
                    </flux:table.column>
                    <flux:table.column>Student</flux:table.column>
                    <flux:table.column>Host Organization</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Duration</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($placements as $placement)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:checkbox wire:model="selectedPlacements" value="{{ $placement->id }}" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $placement->student->full_name }}
                                </div>
                                <div class="text-xs text-gray-500">{{ $placement->student->matric_number }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $placement->organization_display_name }}</div>
                                <div class="text-xs text-gray-500">{{ $placement->placementType->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $stageEnum = \App\Enums\PlacementWorkflowStage::tryFrom($placement->workflow_stage);
                                    $stageColor = $stageEnum ? $stageEnum->color() : 'gray';
                                    $stageLabel = $stageEnum ? $stageEnum->label() : ($placement->workflow_stage ?: 'Assigned');
                                @endphp
                                <flux:badge color="{{ $stageColor }}" size="sm">
                                    {{ $stageLabel }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm">
                                    {{ $placement->start_date->format('M d, Y') }} -
                                    {{ $placement->end_date->format('M d, Y') }}
                                </div>
                                <div class="text-xs text-gray-500">{{ $placement->academic_session }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="openAssignPlaceModal({{ $placement->id }})"
                                            icon="map-pin">
                                            Change Organization
                                        </flux:menu.item>
                                        @if($placement->workflow_stage === 'Pending_Request_Approval')
                                            <flux:menu.item wire:click="approveRequest({{ $placement->id }})" icon="check">
                                                Approve Request
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="openRejectModal({{ $placement->id }}, 'organization')"
                                                icon="x-mark" variant="danger">
                                                Reject Request
                                            </flux:menu.item>
                                        @endif

                                        @if($placement->workflow_stage === 'Acceptance_Submitted' || $placement->placementDocuments->isNotEmpty())
                                            <flux:menu.item wire:click="verifyAcceptanceAndPost({{ $placement->id }})"
                                                icon="check-badge">
                                                Verify Acceptance
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="openRejectModal({{ $placement->id }}, 'acceptance')"
                                                icon="x-mark" variant="danger">
                                                Reject Acceptance
                                            </flux:menu.item>
                                        @endif

                                        @if($placement->organization_id && in_array($placement->workflow_stage, ['Request_Approved', 'Acceptance_Submitted', 'Posting_Issued']))
                                            <flux:menu.item
                                                wire:click="generateGroupCoverLetterForOrganization({{ $placement->organization_id }})"
                                                icon="users">
                                                Group Cover Letter
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.item wire:click="openGenerateModal({{ $placement->id }})"
                                            icon="document-text">
                                            Generate Letter
                                        </flux:menu.item>

                                        @php
                                            $generatedDoc = $placement->generatedDocuments->first();
                                        @endphp
                                        @if($generatedDoc)
                                            <flux:menu.item
                                                href="{{ route('cms.placements.print', ['doc' => $generatedDoc->document_number]) }}"
                                                target="_blank" icon="printer">
                                                Print Letter
                                            </flux:menu.item>
                                        @endif

                                        <flux:menu.separator />

                                        @if($placement->status !== 'Cancelled')
                                            <flux:menu.item wire:click="confirmCancelPlacement({{ $placement->id }})"
                                                icon="no-symbol" variant="danger">
                                                Cancel Placement
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item wire:click="restorePlacement({{ $placement->id }})"
                                                icon="arrow-path">
                                                Restore Placement
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-8 text-gray-500">
                                No placements found matching criteria.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <!-- Create Assignment Modal -->
    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Assign Student</h2>

            <form wire:submit.prevent="savePlacement">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model.live="create_program_id" label="Program">
                        <option value="">Filter by Program...</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="create_level" label="Level">
                        <option value="">Filter by Level...</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                        <option value="500">500 Level</option>
                    </flux:select>

                    <div class="md:col-span-2">
                        <flux:select wire:model="student_id" label="Select Student" required
                            :disabled="empty($modalStudents)">
                            <option value="">
                                {{ empty($modalStudents) ? 'Select Program and Level first...' : 'Choose a student...' }}
                            </option>
                            @foreach($modalStudents as $student)
                                <option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->matric_number }})
                                </option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="md:col-span-2 space-y-3">
                        <div class="flex space-x-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                            <button type="button" wire:click="$set('create_place_mode', 'directory')"
                                class="text-sm font-medium {{ $create_place_mode === 'directory' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500' }}">
                                Choose from Directory
                            </button>
                            <button type="button" wire:click="$set('create_place_mode', 'custom')"
                                class="text-sm font-medium {{ $create_place_mode === 'custom' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500' }}">
                                Enter Custom Organization
                            </button>
                        </div>

                        @if($create_place_mode === 'directory')
                            <flux:select wire:model="organization_id" label="Organization" required>
                                <option value="">Select Organization</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->city }})</option>
                                @endforeach
                            </flux:select>
                        @else
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="md:col-span-2">
                                    <flux:input wire:model="create_custom_name" label="Organization Name"
                                        placeholder="e.g. General Hospital Abuja" required />
                                </div>
                                <div class="md:col-span-2">
                                    <flux:input wire:model="create_custom_address" label="Street Address"
                                        placeholder="e.g. Plot 12, Medical Road" required />
                                </div>
                                <div>
                                    <flux:input wire:model="create_custom_city" label="City" placeholder="e.g. Abuja" />
                                </div>
                                <div>
                                    <flux:input wire:model="create_custom_state" label="State" placeholder="e.g. FCT" />
                                </div>
                            </div>
                        @endif
                    </div>

                    <flux:select wire:model="placement_type_id" label="Placement Type" required>
                        <option value="">Select Type</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="academic_session" label="Academic Session" required>
                        <option value="">Select Session</option>
                        @foreach ($academicSessions as $session)
                            <option value="{{ $session->name }}">
                                {{ $session->name }}{{ $session->status === 'active' ? ' (Active)' : '' }}
                            </option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="start_date" type="date" label="Start Date" required />
                    <flux:input wire:model="end_date" type="date" label="End Date" required />
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Assign Placement</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Assign Place of Posting Modal -->
    <flux:modal wire:model="assignPlaceModal" class="w-full max-w-xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Assign / Edit Place of Posting</h2>
            @if($placementToAssign)
                <p class="text-sm text-gray-500 mb-4">Assigning host facility for <span
                        class="font-semibold text-gray-700 dark:text-gray-300">{{ $placementToAssign->student->full_name }}</span>.
                </p>
            @endif

            <form wire:submit.prevent="saveAssignedPlace">
                <div class="space-y-4">
                    <div class="flex space-x-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                        <button type="button" wire:click="$set('assign_place_mode', 'directory')"
                            class="text-sm font-medium {{ $assign_place_mode === 'directory' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500' }}">
                            Directory Organization
                        </button>
                        <button type="button" wire:click="$set('assign_place_mode', 'custom')"
                            class="text-sm font-medium {{ $assign_place_mode === 'custom' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500' }}">
                            Custom Organization
                        </button>
                    </div>

                    @if($assign_place_mode === 'directory')
                        <flux:select wire:model="assign_organization_id" label="Select Organization" required>
                            <option value="">Choose an organization...</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }} - {{ $org->city }}</option>
                            @endforeach
                        </flux:select>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <flux:input wire:model="assign_custom_name" label="Organization Name"
                                    placeholder="e.g. General Hospital Abuja" required />
                            </div>
                            <div class="md:col-span-2">
                                <flux:input wire:model="assign_custom_address" label="Street Address"
                                    placeholder="e.g. Plot 12, Medical Road" required />
                            </div>
                            <div>
                                <flux:input wire:model="assign_custom_city" label="City" placeholder="e.g. Abuja" />
                            </div>
                            <div>
                                <flux:input wire:model="assign_custom_state" label="State" placeholder="e.g. FCT" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('assignPlaceModal', false)" variant="ghost" class="w-full sm:w-auto">
                        Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Save Assignment</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Bulk Assign Placements Modal -->
    <flux:modal wire:model="batchModal" class="w-full max-w-2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Bulk Assign Placements</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Select a class and choose which students to assign. Leave Organization unselected to let students pick their own host organization.</p>

            <form wire:submit.prevent="processBatchGeneration" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model.live="batch_session" label="Academic Session" required>
                        <option value="">Select Session</option>
                        @foreach ($academicSessions as $session)
                            <option value="{{ $session->name }}">
                                {{ $session->name }}{{ $session->status === 'active' ? ' (Active)' : '' }}
                            </option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="batch_program_id" label="Program" required>
                        <option value="">Select Program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="batch_level" label="Target Level" required>
                        <option value="">Select Level</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                        <option value="500">500 Level</option>
                    </flux:select>

                    <flux:select wire:model="batch_placement_type_id" label="Placement Type" required>
                        <option value="">Select Type</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>

                    <div class="md:col-span-2">
                        <flux:select wire:model="batch_organization_id" label="Default Organization (Optional)">
                            <option value="">-- Let Students Choose Host Organization --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }} - {{ $org->city }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:input wire:model="batch_start_date" type="date" label="Start Date" required />
                    <flux:input wire:model="batch_end_date" type="date" label="End Date" required />
                </div>

                @if ($batch_session && $batch_program_id && $batch_level)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/70 dark:bg-zinc-900/40 p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-white">Select Students</span>
                                <flux:badge size="sm" color="indigo">
                                    {{ count($batch_selected_student_ids) }} / {{ $batchStudents->count() }} selected
                                </flux:badge>
                            </div>
                            @if ($batchStudents->isNotEmpty())
                                <flux:checkbox wire:model.live="batch_select_all" label="Select all" />
                            @endif
                        </div>

                        @if ($batchStudents->isNotEmpty())
                            <flux:input wire:model.live.debounce.200ms="batch_student_search" icon="magnifying-glass"
                                placeholder="Filter students by name or matric..." size="sm" />

                            @php
                                $filteredBatchStudents = $batchStudents->filter(function ($s) {
                                    if (empty(trim($this->batch_student_search))) {
                                        return true;
                                    }
                                    $term = strtolower(trim($this->batch_student_search));
                                    return str_contains(strtolower($s->full_name), $term)
                                        || str_contains(strtolower((string) $s->matric_number), $term);
                                });
                            @endphp

                            <div class="max-h-52 overflow-y-auto divide-y divide-zinc-200/70 dark:divide-zinc-700/70 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                                @forelse ($filteredBatchStudents as $stu)
                                    <label class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60 cursor-pointer">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <input type="checkbox" wire:model.live="batch_selected_student_ids" value="{{ $stu->id }}"
                                                class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800" />
                                            <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $stu->full_name }}</span>
                                        </div>
                                        <span class="shrink-0 text-xs font-mono text-zinc-500 dark:text-zinc-400">{{ $stu->matric_number }}</span>
                                    </label>
                                @empty
                                    <div class="px-3 py-4 text-center text-xs text-zinc-500">No students match "{{ $batch_student_search }}".</div>
                                @endforelse
                            </div>
                        @else
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">No students found for the selected session, program, and level.</p>
                        @endif
                        <flux:error name="batch_selected_student_ids" />
                    </div>
                @endif

                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('batchModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="user-group" class="w-full sm:w-auto">
                        Assign Selected Students</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Generate Letter Modal -->
    <flux:modal wire:model="generateModal" class="w-full max-w-md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Generate Official Letter</h2>

            @if($placementToGenerate)
                <div class="mb-4 text-sm text-gray-600 bg-gray-50 p-3 rounded-md border">
                    Generating for: <strong>{{ $placementToGenerate->student->full_name }}</strong><br>
                    To: <strong>{{ $placementToGenerate->organization_display_name }}</strong>
                </div>

                <div>
                    <flux:field>
                        <flux:select wire:model="selectedTemplateId" label="Select Letter Template">
                            <option value="">Choose a template...</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->title }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedTemplateId" />
                    </flux:field>

                    <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                        <flux:button wire:click="$set('generateModal', false)" variant="ghost" class="w-full sm:w-auto">
                            Cancel</flux:button>
                        <flux:button wire:click="generateLetter" variant="primary" icon="document-text"
                            class="w-full sm:w-auto">Generate PDF</flux:button>
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- Rejection / Re-selection Modal -->
    <flux:modal wire:model="rejectModal" class="w-full max-w-md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                {{ $rejection_type === 'organization' ? 'Reject Organization Selection' : 'Reject Acceptance Scan' }}
            </h2>
            @if($placementToReject)
                <p class="text-sm text-gray-500 mb-4">
                    Please state why this
                    {{ $rejection_type === 'organization' ? 'facility selection' : 'acceptance document' }} is rejected for
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300">{{ $placementToReject->student->full_name }}</span>.
                    This reason will be shown on the student's portal so they can take appropriate corrective action.
                </p>
            @endif

            <form wire:submit.prevent="processRejection">
                <flux:textarea wire:model="rejection_reason" label="Reason for Rejection / Remarks"
                    placeholder="e.g. Facility is not accredited for SIWES or document scan is blurry..." rows="4"
                    required />
                <flux:error name="rejection_reason" />

                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('rejectModal', false)" variant="ghost"
                        class="w-full sm:w-auto order-2 sm:order-1">Cancel</flux:button>
                    <flux:button type="submit" variant="danger" icon="x-mark"
                        class="w-full sm:w-auto order-1 sm:order-2">Confirm Rejection</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Cancel Confirmation Modal -->
    <flux:modal wire:model="cancelModal" class="w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center space-x-3 text-red-600 mb-4">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
                <h2 class="text-lg font-medium">Cancel Placement?</h2>
            </div>
            <p class="text-gray-500 mb-6">Are you sure you want to cancel this student's placement? This action will
                stop their current posting workflow.</p>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                <flux:button wire:click="$set('cancelModal', false)" variant="ghost"
                    class="w-full sm:w-auto order-2 sm:order-1">No, Keep Placement</flux:button>
                <flux:button wire:click="cancelPlacement" variant="danger" class="w-full sm:w-auto order-1 sm:order-2">
                    Yes, Cancel Placement</flux:button>
            </div>
        </div>
    </flux:modal>
</div>