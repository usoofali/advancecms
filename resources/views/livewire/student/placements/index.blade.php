<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\StudentPlacement;
use App\Models\PlacementDocument;
use App\Models\Organization;
use App\Models\DocumentTemplate;
use App\Services\DocumentGenerationService;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $uploadModal = false;
    public $selectPlaceModal = false;
    public $placementToUpload = null;
    public ?StudentPlacement $placementToSelect = null;
    public $documentType = 'Acceptance Letter';
    public $documentFile;

    // Selection fields
    public $selectionMode = 'directory';
    public $selected_organization_id = '';
    public $custom_name = '';
    public $custom_address = '';
    public $custom_city = '';
    public $custom_state = '';

    public function openSelectModal($placementId)
    {
        $student = auth()->user()?->student;
        if (! $student) return;

        $this->placementToSelect = StudentPlacement::where('student_id', $student->id)->find($placementId);
        if (! $this->placementToSelect) {
            Flux::toast('Placement record not found or access denied.', variant: 'danger');
            return;
        }

        $this->selectionMode = 'directory';
        $this->selected_organization_id = $this->placementToSelect->organization_id ?: '';
        $this->custom_name = $this->placementToSelect->custom_organization_name ?: '';
        $this->custom_address = $this->placementToSelect->custom_organization_address ?: '';
        $this->custom_city = $this->placementToSelect->custom_organization_city ?: '';
        $this->custom_state = $this->placementToSelect->custom_organization_state ?: '';
        $this->selectPlaceModal = true;
    }

    public function savePlaceSelection()
    {
        $student = auth()->user()?->student;
        if (! $student || ! $this->placementToSelect || $this->placementToSelect->student_id !== $student->id) {
            Flux::toast('Unauthorized action.', variant: 'danger');
            return;
        }

        if ($this->selectionMode === 'directory') {
            $this->validate([
                'selected_organization_id' => 'required|exists:organizations,id'
            ], [
                'selected_organization_id.required' => 'Please select an organization from the directory.'
            ]);

            $this->placementToSelect->update([
                'organization_id' => $this->selected_organization_id,
                'custom_organization_name' => null,
                'custom_organization_address' => null,
                'custom_organization_city' => null,
                'custom_organization_state' => null,
                'workflow_stage' => 'Pending_Request_Approval',
                'status' => 'Assigned',
                'admin_remarks' => null,
            ]);
        } else {
            $this->validate([
                'custom_name' => 'required|string|max:255',
                'custom_address' => 'required|string|max:500',
                'custom_city' => 'nullable|string|max:100',
                'custom_state' => 'nullable|string|max:100',
            ]);

            $this->placementToSelect->update([
                'organization_id' => null,
                'custom_organization_name' => $this->custom_name,
                'custom_organization_address' => $this->custom_address,
                'custom_organization_city' => $this->custom_city,
                'custom_organization_state' => $this->custom_state,
                'workflow_stage' => 'Pending_Request_Approval',
                'status' => 'Assigned',
                'admin_remarks' => null,
            ]);
        }

        Flux::toast('Place of posting selected successfully. Awaiting Admin approval.', variant: 'success');
        $this->selectPlaceModal = false;
    }

    public function downloadDocument($placementId, $purpose, DocumentGenerationService $generator)
    {
        $student = auth()->user()?->student;
        if (! $student) return;

        $placement = StudentPlacement::where('student_id', $student->id)->find($placementId);
        if (! $placement) {
            Flux::toast('Placement record not found or access denied.', variant: 'danger');
            return;
        }

        // Find existing generated document or generate one if template exists
        $doc = $placement->generatedDocuments()->where('purpose', $purpose)->first();

        if (!$doc) {
            $templateType = match($purpose) {
                'request' => 'Hospital', // Or default category matching placementType
                'acceptance_form' => 'Acceptance Form',
                'posting' => 'Posting Letter',
                default => 'Other'
            };

            $template = DocumentTemplate::where('type', $templateType)->where('active', true)->first()
                        ?? DocumentTemplate::where('type', 'Other')->where('active', true)->first();

            if (!$template) {
                Flux::toast("Template not configured for this document type ({$purpose}).", variant: 'danger');
                return;
            }

            $doc = $generator->generateRecord($placement, $template, $purpose);
        }

        return redirect()->route('cms.placements.print', ['doc' => $doc->document_number]);
    }

    public function openUploadModal($placementId)
    {
        $student = auth()->user()?->student;
        if (! $student) return;

        $this->placementToUpload = StudentPlacement::where('student_id', $student->id)->find($placementId);
        if (! $this->placementToUpload) {
            Flux::toast('Placement record not found or access denied.', variant: 'danger');
            return;
        }

        $this->documentType = 'Acceptance Letter';
        $this->documentFile = null;
        $this->uploadModal = true;
    }

    public function uploadDocument()
    {
        $student = auth()->user()?->student;
        if (! $student || ! $this->placementToUpload || $this->placementToUpload->student_id !== $student->id) {
            Flux::toast('Unauthorized action.', variant: 'danger');
            return;
        }

        $this->validate([
            'documentType' => 'required|string',
            'documentFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $path = $this->documentFile->store('placement_documents', 'public');

        PlacementDocument::create([
            'placement_id' => $this->placementToUpload->id,
            'type' => $this->documentType,
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
        ]);
        
        // Auto-update placement status & workflow stage
        if ($this->documentType === 'Acceptance Letter') {
            $this->placementToUpload->update([
                'status' => 'Accepted',
                'workflow_stage' => 'Acceptance_Submitted',
                'admin_remarks' => null,
            ]);
        }

        Flux::toast('Document uploaded successfully.', variant: 'success');
        $this->uploadModal = false;
    }

    public function with(): array
    {
        $student = auth()->user()?->student;
        
        return [
            'placements' => $student ? StudentPlacement::with(['organization', 'placementType', 'generatedDocuments', 'placementDocuments'])
                ->where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->get() : collect(),
            'organizations' => Organization::orderBy('name')->get()
        ];
    }
};
?>

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">My Placements</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View your industrial training and clinical postings, choose your place of posting, download letters, and upload acceptance forms.</p>
    </div>

    @if($placements->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.document-text class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Placements Yet</h3>
            <p class="text-gray-500 mt-2">You have not been initiated for any placement or training program yet.</p>
        </flux:card>
    @else
        <div class="space-y-6">
            @foreach($placements as $placement)
                <flux:card>
                    @if($placement->admin_remarks)
                        <div class="mb-4 p-4 rounded-lg border {{ $placement->status === 'Cancelled' ? 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300' : 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-yellow-300' }}">
                            <div class="flex items-start">
                                <flux:icon.exclamation-triangle class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 {{ $placement->status === 'Cancelled' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}" />
                                <div>
                                    <h4 class="font-semibold text-sm">
                                        @if($placement->status === 'Cancelled')
                                            Placement Cancelled
                                        @elseif($placement->workflow_stage === 'Pending_Selection')
                                            Action Required: Organization Selection Rejected
                                        @elseif($placement->workflow_stage === 'Request_Approved')
                                            Action Required: Acceptance Document Rejected
                                        @else
                                            Administrator Remarks
                                        @endif
                                    </h4>
                                    <p class="text-sm mt-1">{{ $placement->admin_remarks }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center space-x-3 mb-2">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ $placement->organization_display_name }}
                                </h3>
                                @php
                                    $badgeColor = match($placement->workflow_stage) {
                                        'Pending_Selection' => 'red',
                                        'Pending_Request_Approval' => 'yellow',
                                        'Request_Approved' => 'blue',
                                        'Acceptance_Submitted' => 'purple',
                                        'Posting_Issued' => 'green',
                                        default => 'gray'
                                    };
                                    $stageLabel = str_replace('_', ' ', $placement->workflow_stage ?: $placement->status);
                                @endphp
                                <flux:badge color="{{ $badgeColor }}">{{ $stageLabel }}</flux:badge>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase">Placement Type</p>
                                    <p class="font-medium">{{ $placement->placementType->name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase">Academic Session</p>
                                    <p class="font-medium">{{ $placement->academic_session }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase">Duration</p>
                                    <p class="font-medium">{{ $placement->start_date->format('M d, Y') }} to {{ $placement->end_date->format('M d, Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase">Location</p>
                                    <p class="font-medium">{{ $placement->organization_display_address }}</p>
                                </div>
                                <div class="col-span-1 md:col-span-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <p class="text-xs font-medium text-gray-500 uppercase mb-1">Academic Supervisor</p>
                                    @php
                                        $supervisorRule = app(\App\Services\PlacementSupervisorResolver::class)->resolveForPlacement($placement);
                                        $supervisor = $supervisorRule?->supervisor;
                                    @endphp
                                    @if($supervisor)
                                        <div class="flex items-center space-x-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            <span>{{ $supervisor->name }} ({{ $supervisor->email }})</span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Awaiting Supervisor Assignment
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col space-y-3 min-w-[220px]">
                            @if($placement->workflow_stage === 'Pending_Selection' || empty($placement->organization_display_name) || $placement->organization_display_name === 'Pending Organization Selection')
                                <flux:button wire:click="openSelectModal({{ $placement->id }})" variant="primary" icon="building-office">
                                    Select Place of Posting
                                </flux:button>
                            @elseif($placement->workflow_stage === 'Pending_Request_Approval')
                                <div class="text-sm p-3 bg-yellow-50 text-yellow-800 rounded-md border border-yellow-200">
                                    Your choice of organization is pending Admin approval.
                                </div>
                                <flux:button wire:click="openSelectModal({{ $placement->id }})" variant="ghost" size="sm" icon="pencil">
                                    Change Organization
                                </flux:button>
                            @elseif($placement->workflow_stage === 'Request_Approved')
                                <flux:button wire:click="downloadDocument({{ $placement->id }}, 'request')" variant="primary" icon="document-text">
                                    Download Request Letter
                                </flux:button>
                                <flux:button wire:click="downloadDocument({{ $placement->id }}, 'acceptance_form')" variant="outline" icon="document">
                                    Download Acceptance Form
                                </flux:button>
                                <flux:button wire:click="openUploadModal({{ $placement->id }})" variant="ghost" icon="arrow-up-tray">
                                    Upload Signed Acceptance
                                </flux:button>
                            @elseif($placement->workflow_stage === 'Acceptance_Submitted')
                                <div class="text-sm p-3 bg-purple-50 text-purple-800 rounded-md border border-purple-200">
                                    Acceptance uploaded! Under Admin verification.
                                </div>
                                <flux:button wire:click="downloadDocument({{ $placement->id }}, 'request')" variant="ghost" size="sm" icon="document-text">
                                    Request Letter
                                </flux:button>
                                <flux:button wire:click="openUploadModal({{ $placement->id }})" variant="ghost" size="sm" icon="arrow-up-tray">
                                    Re-upload Acceptance
                                </flux:button>
                            @elseif($placement->workflow_stage === 'Posting_Issued' || $placement->status === 'Accepted')
                                <flux:button wire:click="downloadDocument({{ $placement->id }}, 'posting')" variant="primary" icon="check-badge">
                                    Download Posting Letter
                                </flux:button>
                                <flux:button wire:click="downloadDocument({{ $placement->id }}, 'request')" variant="ghost" size="sm" icon="document-text">
                                    Request Letter
                                </flux:button>
                            @else
                                @if($placement->generatedDocuments->isNotEmpty())
                                    @foreach($placement->generatedDocuments as $doc)
                                        <flux:button href="{{ route('cms.placements.print', ['doc' => $doc->document_number]) }}" target="_blank" variant="primary" icon="printer">
                                            Print / Download Letter
                                        </flux:button>
                                    @endforeach
                                @endif
                                <flux:button wire:click="openUploadModal({{ $placement->id }})" variant="ghost" icon="arrow-up-tray">
                                    Upload Document
                                </flux:button>
                            @endif
                        </div>
                    </div>
                    
                    @if($placement->placementDocuments->isNotEmpty())
                        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Uploaded Documents</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach($placement->placementDocuments as $doc)
                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="flex items-center space-x-3 p-3 rounded-md border border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800 transition">
                                        <flux:icon.document class="w-5 h-5 text-gray-400" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $doc->type }}</p>
                                            <p class="text-xs text-gray-500">{{ $doc->uploaded_at->diffForHumans() }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    <!-- Select Place of Posting Modal -->
    <flux:modal wire:model="selectPlaceModal" class="w-full max-w-lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Select Place of Posting</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Choose an organization from the directory or enter custom organization details if your preferred place is not listed.</p>
            
            <form wire:submit.prevent="savePlaceSelection">
                <div class="space-y-4">
                    <flux:radio.group wire:model.live="selectionMode" label="Selection Method">
                        <flux:radio value="directory" label="Select from Directory" />
                        <flux:radio value="custom" label="Enter Custom Organization" />
                    </flux:radio.group>

                    @if($selectionMode === 'directory')
                        <div class="mt-4">
                            <flux:select wire:model="selected_organization_id" label="Organization" required>
                                <option value="">-- Choose Organization --</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->city }}, {{ $org->state }})</option>
                                @endforeach
                            </flux:select>
                        </div>
                    @else
                        <div class="space-y-3 mt-4 border-t pt-4">
                            <flux:input wire:model="custom_name" label="Organization Name" placeholder="e.g. National Hospital Abuja" required />
                            <flux:input wire:model="custom_address" label="Street Address" placeholder="e.g. Plot 132, Central Business District" required />
                            <div class="grid grid-cols-2 gap-3">
                                <flux:input wire:model="custom_city" label="City" placeholder="e.g. Abuja" />
                                <flux:input wire:model="custom_state" label="State" placeholder="e.g. FCT" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('selectPlaceModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Submit Selection</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Upload Modal -->
    <flux:modal wire:model="uploadModal" class="w-full max-w-md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Upload Document</h2>
            
            <form wire:submit.prevent="uploadDocument">
                <div class="space-y-4">
                    <flux:select wire:model="documentType" label="Document Type" required>
                        <option value="Acceptance Letter">Acceptance Letter / Form</option>
                        <option value="Rejection Letter">Rejection Letter</option>
                        <option value="Logbook Page">Logbook Page</option>
                        <option value="Final Report">Final Report</option>
                        <option value="Other">Other</option>
                    </flux:select>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">File</label>
                        <input type="file" wire:model="documentFile" class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100
                            dark:file:bg-slate-800 dark:file:text-slate-300" required>
                        <div wire:loading wire:target="documentFile" class="text-sm text-gray-500">Uploading...</div>
                        @error('documentFile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('uploadModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" class="w-full sm:w-auto">Upload File</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
