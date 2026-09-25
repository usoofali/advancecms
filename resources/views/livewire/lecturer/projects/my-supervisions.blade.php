<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Staff;
use App\Models\StudentProject;
use App\Models\ProjectSubmission;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';
    public string $status_filter = '';

    // Review modal state
    public bool $showReviewModal = false;
    public ?int $reviewSubmissionId = null;
    public string $review_status = 'Approved'; // 'Approved', 'Corrections Required'
    public ?string $supervisor_feedback = null;
    public $supervisor_file = null;

    public function openReview(ProjectSubmission $submission): void
    {
        $this->reviewSubmissionId = $submission->id;
        $this->review_status = 'Approved';
        $this->supervisor_feedback = '';
        $this->supervisor_file = null;
        $this->showReviewModal = true;
    }

    public function submitReview(): void
    {
        $this->validate([
            'review_status' => 'required|in:Approved,Corrections Required',
            'supervisor_feedback' => 'nullable|string',
            'supervisor_file' => 'nullable|file|mimes:pdf,doc,docx,zip,rar|max:10240',
        ]);

        if (! $this->reviewSubmissionId) return;

        $submission = ProjectSubmission::with(['studentProject', 'stage'])->findOrFail($this->reviewSubmissionId);
        $project = $submission->studentProject;

        $filePath = null;
        if ($this->supervisor_file) {
            $filePath = $this->supervisor_file->store('project_annotations', 'public');
        }

        $submission->update([
            'status' => $this->review_status,
            'supervisor_feedback' => $this->supervisor_feedback,
            'supervisor_file_path' => $filePath ?? $submission->supervisor_file_path,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        if ($this->review_status === 'Approved') {
            // Move project to next stage if available
            $currentStageOrder = $submission->stage->stage_order;
            $nextStage = $project->projectSession->stages()
                ->where('stage_order', '>', $currentStageOrder)
                ->orderBy('stage_order')
                ->first();

            if ($nextStage) {
                $project->update([
                    'current_stage_id' => $nextStage->id,
                    'overall_status' => 'In Progress',
                ]);
            }

            $project->updateProgress();
            $project->logActivity('Stage Approved', "Stage '{$submission->stage->title}' approved by supervisor.");
        } else {
            $project->update(['overall_status' => 'Corrections Required']);
            $project->logActivity('Corrections Requested', "Corrections requested for stage '{$submission->stage->title}'. Feedback: {$this->supervisor_feedback}");
        }

        $this->showReviewModal = false;
        Flux::toast('Submission review submitted successfully.');
    }

    public function with(): array
    {
        $user = auth()->user();
        $staff = Staff::where('email', $user->email)->first();

        $staffId = $staff?->id;

        $projects = StudentProject::with(['student', 'program', 'approvedTopic', 'currentStage', 'submissions'])
            ->when($staffId, fn($q) => $q->where('supervisor_id', $staffId))
            ->when($this->status_filter, fn($q) => $q->where('overall_status', $this->status_filter))
            ->when($this->search, function ($q) {
                $q->whereHas('student', function ($sq) {
                    $sq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('matric_number', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(12);

        $pendingSubmissions = ProjectSubmission::with(['studentProject.student', 'stage'])
            ->whereHas('studentProject', fn($q) => $q->where('supervisor_id', $staffId))
            ->where('status', 'Submitted')
            ->latest()
            ->get();

        return compact('projects', 'pendingSubmissions');
    }
};
?>

<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">My Project Supervisions</h2>
            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Review student chapter submissions, provide annotated feedback, and track stage approvals.</p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search supervisee..." icon="magnifying-glass" class="w-full sm:w-64" />
        </div>
    </div>

    <!-- Pending Reviews Section -->
    @if($pendingSubmissions->isNotEmpty())
        <div class="mb-8 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 sm:p-6">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon.clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">Pending Submissions Awaiting Review ({{ $pendingSubmissions->count() }})</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($pendingSubmissions as $sub)
                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-amber-200 dark:border-amber-800/60 shadow-sm flex flex-col justify-between gap-4">
                        <div>
                            <span class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $sub->studentProject->student->first_name }} {{ $sub->studentProject->student->last_name }}</span>
                            <div class="text-xs text-gray-500 font-mono">{{ $sub->studentProject->student->matric_number }}</div>
                            <div class="mt-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                <flux:icon.document-text class="w-4 h-4" /> {{ $sub->stage->title }} (Version {{ $sub->version_number }})
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">Submitted: {{ $sub->created_at->format('M d, Y h:i A') }}</p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                            @if($sub->file_path)
                                <a href="{{ Storage::url($sub->file_path) }}" target="_blank" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-medium">
                                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5" /> Download File
                                </a>
                            @endif
                            <flux:button wire:click="openReview({{ $sub->id }})" variant="primary" size="sm">
                                Review & Feedback
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Supervisees Roster Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($projects as $proj)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $proj->student->first_name }} {{ $proj->student->last_name }}</h4>
                            <p class="text-xs text-gray-500 font-mono">{{ $proj->student->matric_number }}</p>
                        </div>
                        <flux:badge :variant="$proj->overall_status === 'Completed' ? 'success' : 'warning'">
                            {{ $proj->overall_status }}
                        </flux:badge>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <p class="text-xs text-gray-500 font-medium">Approved Topic Title:</p>
                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 mt-0.5 line-clamp-2">
                            {{ $proj->approvedTopic?->topic_title ?: 'Topic Pending Approval' }}
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="flex justify-between text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            <span>Stage: {{ $proj->currentStage?->title ?: 'N/A' }}</span>
                            <span>{{ $proj->progress_percentage }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $proj->progress_percentage }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $proj->submissions->count() }} Total Submissions</span>
                    <flux:button href="{{ route('cms.projects.show', $proj->id) }}" variant="subtle" size="sm" icon="eye">
                        Open Workspace
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-gray-800 p-8 sm:p-12 text-center">
                <flux:icon.user-group class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <p class="text-base font-medium text-gray-600 dark:text-gray-400">No assigned supervisees found.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $projects->links() }}
    </div>

    <!-- Review Modal -->
    <flux:modal wire:model="showReviewModal" class="w-full max-w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Supervisor Review & Feedback</h3>
                <p class="text-xs text-gray-500">Provide review comments and upload corrected/annotated document if needed.</p>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="review_status" label="Stage Approval Action">
                    <option value="Approved">Approve Stage</option>
                    <option value="Corrections Required">Request Corrections</option>
                </flux:select>

                <flux:textarea wire:model="supervisor_feedback" label="Feedback / Instructions" rows="4" placeholder="Enter comments or revision guidance for the student..." />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Annotated Document (Optional)</label>
                    <input type="file" wire:model="supervisor_file" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950 dark:file:text-indigo-300 hover:file:bg-indigo-100" />
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <flux:button wire:click="$set('showReviewModal', false)" variant="subtle">Cancel</flux:button>
                <flux:button wire:click="submitReview" variant="primary">Save Review</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
