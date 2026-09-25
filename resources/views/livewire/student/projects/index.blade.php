<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Student;
use App\Models\ProjectSession;
use App\Models\StudentProject;
use App\Models\ProjectTopic;
use App\Models\ProjectSubmission;
use App\Models\ProjectMessage;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public string $activeTab = 'topics'; // 'topics', 'chapters', 'chat'

    // Topic Submission Form
    public string $topic_1 = '';
    public ?string $desc_1 = null;
    public string $topic_2 = '';
    public ?string $desc_2 = null;
    public string $topic_3 = '';
    public ?string $desc_3 = null;

    // Chapter Submission Form
    public ?int $selected_stage_id = null;
    public $submission_file = null;
    public ?string $student_remarks = null;

    // Chat Message Form
    public string $chat_message = '';
    public $chat_attachment = null;

    public function mount(): void
    {
        $project = $this->getStudentProject();
        if ($project) {
            $t1 = $project->topics->where('topic_order', 1)->first();
            $t2 = $project->topics->where('topic_order', 2)->first();
            $t3 = $project->topics->where('topic_order', 3)->first();

            $this->topic_1 = $t1?->topic_title ?? '';
            $this->desc_1 = $t1?->description ?? '';
            $this->topic_2 = $t2?->topic_title ?? '';
            $this->desc_2 = $t2?->description ?? '';
            $this->topic_3 = $t3?->topic_title ?? '';
            $this->desc_3 = $t3?->description ?? '';

            if ($project->approvedTopic) {
                $this->activeTab = 'chapters';
            }
        }
    }

    public function updatedActiveTab(string $tab): void
    {
        if ($tab === 'chat') {
            $project = $this->getStudentProject();
            $project?->markMessagesAsReadForUser(auth()->id());
        }
    }

    public function registerSelf(): void
    {
        $user = auth()->user();
        $student = Student::where('email', $user->email)->first();
        if (! $student) {
            Flux::toast('No matching student record found.', variant: 'danger');
            return;
        }

        $activeSession = ProjectSession::where('institution_id', $user->institution_id)
            ->where('status', 'Active')
            ->first();

        if (! $activeSession) {
            Flux::toast('No active project session available.', variant: 'warning');
            return;
        }

        $project = StudentProject::selfRegister($student, $activeSession);
        if ($project) {
            Flux::toast('You have been successfully registered for the project session.', variant: 'success');
        } else {
            Flux::toast('Ineligible for project session registration.', variant: 'danger');
        }
    }

    public function submitTopics(): void
    {
        $this->validate([
            'topic_1' => 'required|string|max:255',
            'desc_1' => 'nullable|string',
            'topic_2' => 'nullable|string|max:255',
            'desc_2' => 'nullable|string',
            'topic_3' => 'nullable|string|max:255',
            'desc_3' => 'nullable|string',
        ]);

        $project = $this->getStudentProject();
        if (! $project) return;

        // Clear previous unapproved topics
        ProjectTopic::where('student_project_id', $project->id)
            ->whereIn('status', ['Submitted', 'Rejected', 'Modification Requested'])
            ->delete();

        ProjectTopic::create([
            'student_project_id' => $project->id,
            'topic_title' => $this->topic_1,
            'description' => $this->desc_1,
            'topic_order' => 1,
            'status' => 'Submitted',
        ]);

        if ($this->topic_2) {
            ProjectTopic::create([
                'student_project_id' => $project->id,
                'topic_title' => $this->topic_2,
                'description' => $this->desc_2,
                'topic_order' => 2,
                'status' => 'Submitted',
            ]);
        }

        if ($this->topic_3) {
            ProjectTopic::create([
                'student_project_id' => $project->id,
                'topic_title' => $this->topic_3,
                'description' => $this->desc_3,
                'topic_order' => 3,
                'status' => 'Submitted',
            ]);
        }

        $project->update(['overall_status' => 'Topic Pending']);
        $project->logActivity('Topics Submitted', 'Submitted proposed project topics for approval.', auth()->id());

        Flux::toast('Your project topics have been submitted successfully for coordinator review.', variant: 'success');
    }

    public function uploadChapterSubmission(): void
    {
        $this->validate([
            'selected_stage_id' => 'required|exists:project_session_stages,id',
            'submission_file' => 'required|file|mimes:pdf,doc,docx,zip,rar|max:15360',
            'student_remarks' => 'nullable|string',
        ]);

        $project = $this->getStudentProject();
        if (! $project) return;

        $latestVersion = ProjectSubmission::where('student_project_id', $project->id)
            ->where('stage_id', $this->selected_stage_id)
            ->max('version_number') ?? 0;

        $newVersion = $latestVersion + 1;
        $originalName = $this->submission_file->getClientOriginalName();
        $path = $this->submission_file->store('project_submissions', 'public');

        ProjectSubmission::create([
            'student_project_id' => $project->id,
            'stage_id' => $this->selected_stage_id,
            'version_number' => $newVersion,
            'student_remarks' => $this->student_remarks,
            'file_path' => $path,
            'file_original_name' => $originalName,
            'status' => 'Submitted',
        ]);

        $project->update([
            'current_stage_id' => $this->selected_stage_id,
            'overall_status' => 'In Progress',
        ]);

        $project->logActivity('Chapter Submission Uploaded', "Uploaded version {$newVersion} for stage.", auth()->id());

        $this->submission_file = null;
        $this->student_remarks = '';
        Flux::toast('Chapter submission uploaded successfully.', variant: 'success');
    }

    public function sendMessage(): void
    {
        $this->validate([
            'chat_message' => 'required|string',
            'chat_attachment' => 'nullable|file|max:10240',
        ]);

        $project = $this->getStudentProject();
        if (! $project) return;

        $attachmentPath = null;
        $attachmentName = null;
        if ($this->chat_attachment) {
            $attachmentName = $this->chat_attachment->getClientOriginalName();
            $attachmentPath = $this->chat_attachment->store('project_messages', 'public');
        }

        ProjectMessage::create([
            'student_project_id' => $project->id,
            'sender_id' => auth()->id(),
            'message' => $this->chat_message,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $this->chat_message = '';
        $this->chat_attachment = null;
        Flux::toast('Message sent successfully.', variant: 'success');
    }

    protected function getStudentProject(): ?StudentProject
    {
        $user = auth()->user();
        $student = Student::where('email', $user->email)->first();
        if (! $student) return null;

        $activeSession = ProjectSession::where('institution_id', $user->institution_id)
            ->where('status', 'Active')
            ->first();

        if (! $activeSession) return null;

        return StudentProject::with(['projectSession.stages', 'approvedTopic', 'supervisor', 'topics', 'submissions.stage', 'messages.sender'])
            ->where('student_id', $student->id)
            ->where('project_session_id', $activeSession->id)
            ->first();
    }

    public function with(): array
    {
        $user = auth()->user();
        $student = Student::with(['program', 'department'])->where('email', $user->email)->first();

        $activeSession = ProjectSession::with('academicSession')
            ->where('institution_id', $user->institution_id)
            ->where('status', 'Active')
            ->first();

        $eligibility = ($student && $activeSession)
            ? StudentProject::checkEligibility($student, $activeSession->academicSession)
            : ['eligible' => false, 'reason' => 'No active project session or student record found.'];

        $project = $this->getStudentProject();

        if (! $this->selected_stage_id && $project?->current_stage_id) {
            $this->selected_stage_id = $project->current_stage_id;
        }

        $unreadCount = $project ? $project->unreadMessagesCountForUser(auth()->id()) : 0;

        return compact('student', 'activeSession', 'eligibility', 'project', 'unreadCount');
    }
};
?>

<div>
    <div class="mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">My Academic Project Workspace</h2>
        <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">Manage topic proposals, submit chapter documents, view supervisor feedback, and communicate with your supervisor.</p>
    </div>

    @if(!$student)
        <div class="p-4 sm:p-6 bg-red-50 text-red-700 rounded-xl border border-red-200 text-sm">
            No matching student profile found for your account email address.
        </div>
    @elseif(!$activeSession)
        <div class="p-4 sm:p-6 bg-amber-50 text-amber-700 rounded-xl border border-amber-200 text-sm">
            There is currently no active academic project session open for registration.
        </div>
    @elseif(!$eligibility['eligible'])
        <!-- Eligibility Warning Card -->
        <div class="bg-red-50/80 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 rounded-xl p-4 sm:p-6 text-red-900 dark:text-red-200">
            <div class="flex flex-col sm:flex-row items-start gap-4">
                <div class="p-3 bg-red-100 dark:bg-red-900/50 rounded-full text-red-600 dark:text-red-400 shrink-0">
                    <flux:icon.x-circle class="w-7 h-7 sm:w-8 sm:h-8" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold">Ineligible for Project Registration</h3>
                    <p class="text-xs sm:text-sm mt-1">{{ $eligibility['reason'] }}</p>
                    <div class="mt-4 p-4 bg-white/70 dark:bg-zinc-900/60 rounded-lg text-xs space-y-1 font-mono text-gray-700 dark:text-gray-300">
                        <div>Enrolled Program: <span class="px-2 py-0.5 font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">{{ $student->program?->acronym ?? $student->program?->name }}</span> ({{ $student->program?->duration_years }} Year Duration)</div>
                        <div>Your Calculated Current Level: {{ $eligibility['current_level'] ?? 'N/A' }} Level</div>
                        <div>Required Final Year Level: {{ $eligibility['required_level'] ?? 'N/A' }} Level</div>
                    </div>
                </div>
            </div>
        </div>
    @elseif(!$project)
        <!-- Registration Action Card -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 sm:p-8 text-center">
            <flux:icon.academic-cap class="w-12 h-12 text-indigo-600 dark:text-indigo-400 mx-auto mb-3" />
            <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">Eligible for Academic Project Session</h3>
            <p class="text-xs sm:text-sm text-gray-500 max-w-lg mx-auto mt-2 mb-6">You are eligible to participate in the {{ $activeSession->title }}. Click below to initialize your project workspace.</p>
            <flux:button wire:click="registerSelf" variant="primary" icon="plus-circle" class="w-full sm:w-auto">
                Register for Academic Project Session
            </flux:button>
        </div>
    @else
        <!-- Active Project Workspace Header -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Approved Official Topic Title</span>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $project->approvedTopic?->topic_title ?: 'Pending Topic Approval' }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-2">Department: {{ $project->department->name }} | Program: <span class="px-2 py-0.5 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">{{ $project->program->acronym ?? $project->program->name }}</span></p>
                </div>

                <div class="p-4 bg-indigo-50/50 dark:bg-zinc-800/40 rounded-xl border border-indigo-100 dark:border-zinc-700/50 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-gray-500 font-medium">Assigned Supervisor</span>
                        <p class="font-bold text-gray-900 dark:text-white text-sm mt-0.5">
                            {{ $project->supervisor ? $project->supervisor->first_name . ' ' . $project->supervisor->last_name : 'Unassigned' }}
                        </p>
                        @if($project->supervisor)
                            <p class="text-[11px] text-gray-500">{{ $project->supervisor->email }}</p>
                        @endif
                    </div>
                    <div class="p-2.5 bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300 rounded-full shrink-0">
                        <flux:icon.user-plus class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800">
                <div class="flex justify-between text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    <span>Overall Project Progress</span>
                    <span>{{ $project->progress_percentage }}% Completed</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                    <div class="bg-indigo-600 h-2.5 rounded-full transition-all" style="width: {{ $project->progress_percentage }}%"></div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-gray-200 dark:border-gray-800 mb-6 gap-3 sm:gap-6 overflow-x-auto whitespace-nowrap scrollbar-none pb-1">
            <button wire:click="$set('activeTab', 'topics')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'topics' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                Project Topic Proposals
            </button>
            <button wire:click="$set('activeTab', 'chapters')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'chapters' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                Chapter Submissions & Feedback
            </button>
            <button wire:click="$set('activeTab', 'chat')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition flex items-center gap-2 {{ $activeTab === 'chat' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
                <span>Project Messages & Chat</span>
                @if($unreadCount > 0)
                    <span class="px-2 py-0.5 text-xs font-bold bg-rose-500 text-white rounded-full">{{ $unreadCount }}</span>
                @endif
            </button>
        </div>

        <!-- Tab 1: Topic Proposals -->
        @if($activeTab === 'topics')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Submit Form -->
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-2">Submit Proposed Topics</h3>
                    <p class="text-xs text-gray-500 mb-6">Submit up to 3 proposed project topics for Project Coordinator review.</p>

                    <div class="space-y-4">
                        <div>
                            <flux:input wire:model="topic_1" label="Proposed Topic Option 1 (Primary)" placeholder="Enter primary topic title..." />
                            <flux:textarea wire:model="desc_1" label="Brief Problem Statement / Scope" rows="2" class="mt-2" />
                        </div>

                        <div>
                            <flux:input wire:model="topic_2" label="Proposed Topic Option 2 (Alternative)" placeholder="Enter secondary topic title..." />
                            <flux:textarea wire:model="desc_2" label="Brief Problem Statement / Scope" rows="2" class="mt-2" />
                        </div>

                        <div>
                            <flux:input wire:model="topic_3" label="Proposed Topic Option 3 (Alternative)" placeholder="Enter tertiary topic title..." />
                            <flux:textarea wire:model="desc_3" label="Brief Problem Statement / Scope" rows="2" class="mt-2" />
                        </div>

                        <div class="pt-4 border-t">
                            <flux:button wire:click="submitTopics" variant="primary" class="w-full">
                                Submit Proposed Topics
                            </flux:button>
                        </div>
                    </div>
                </div>

                <!-- Submitted Topics List -->
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Topic Proposals Status</h3>

                    <div class="space-y-4">
                        @forelse($project->topics as $tpc)
                            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-zinc-800/40">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Option {{ $tpc->topic_order }}</span>
                                    <flux:badge :variant="$tpc->status === 'Approved' ? 'success' : ($tpc->status === 'Rejected' ? 'danger' : 'warning')">
                                        {{ $tpc->status }}
                                    </flux:badge>
                                </div>
                                <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base mt-1">{{ $tpc->topic_title }}</h4>
                                <p class="text-xs text-gray-500 mt-1">{{ $tpc->description }}</p>

                                @if($tpc->feedback)
                                    <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-lg text-xs text-amber-900 dark:text-amber-200">
                                        <span class="font-semibold">Coordinator Feedback:</span> {{ $tpc->feedback }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-8">No proposed topics submitted yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- Tab 2: Chapter Submissions -->
        @if($activeTab === 'chapters')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Upload Form -->
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Submit Chapter Document</h3>

                    <div class="space-y-4">
                        <flux:select wire:model="selected_stage_id" label="Project Stage / Chapter">
                            @foreach($project->projectSession->stages as $stg)
                                <option value="{{ $stg->id }}">{{ $stg->stage_order }}. {{ $stg->title }}</option>
                            @endforeach
                        </flux:select>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Document File (PDF/DOCX)</label>
                            <input type="file" wire:model="submission_file" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950 dark:file:text-indigo-300 hover:file:bg-indigo-100" />
                        </div>

                        <flux:textarea wire:model="student_remarks" label="Student Remarks / Notes for Supervisor" rows="3" placeholder="Enter any notes regarding this submission..." />

                        <flux:button wire:click="uploadChapterSubmission" variant="primary" class="w-full">
                            Upload Chapter Submission
                        </flux:button>
                    </div>
                </div>

                <!-- Submissions History -->
                <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Chapter Submission & Feedback History</h3>

                    <div class="space-y-4">
                        @forelse($project->submissions as $sub)
                            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-zinc-800/40">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $sub->stage->title }}</span>
                                        <flux:badge variant="subtle">Version {{ $sub->version_number }}</flux:badge>
                                    </div>
                                    <flux:badge :variant="$sub->status === 'Approved' ? 'success' : ($sub->status === 'Corrections Required' ? 'danger' : 'warning')">
                                        {{ $sub->status }}
                                    </flux:badge>
                                </div>

                                <div class="mt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs text-gray-500">
                                    <span>Submitted: {{ $sub->created_at->format('M d, Y h:i A') }}</span>
                                    @if($sub->file_path)
                                        <a href="{{ Storage::url($sub->file_path) }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline flex items-center gap-1">
                                            <flux:icon.arrow-down-tray class="w-3.5 h-3.5" /> Download Student File
                                        </a>
                                    @endif
                                </div>

                                @if($sub->supervisor_feedback)
                                    <div class="mt-3 p-3 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-lg text-xs">
                                        <span class="font-bold text-gray-800 dark:text-gray-200">Supervisor Feedback:</span>
                                        <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $sub->supervisor_feedback }}</p>

                                        @if($sub->supervisor_file_path)
                                            <div class="mt-2 pt-2 border-t text-xs">
                                                <a href="{{ Storage::url($sub->supervisor_file_path) }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1">
                                                    <flux:icon.paper-clip class="w-3.5 h-3.5" /> Download Supervisor Annotated File
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-8">No chapter submissions uploaded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- Tab 3: Chat / Communication -->
        @if($activeTab === 'chat')
            <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Project Communication Hub</h3>

                <div class="space-y-4 max-h-96 overflow-y-auto p-4 border border-gray-100 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-zinc-800/30 mb-4">
                    @forelse($project->messages as $msg)
                        <div class="flex flex-col {{ $msg->sender_id === auth()->id() ? 'items-end' : 'items-start' }}">
                            <div class="max-w-xs sm:max-w-md p-3 rounded-2xl text-xs {{ $msg->sender_id === auth()->id() ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-tl-none' }}">
                                <div class="font-bold text-[10px] opacity-75 mb-1">{{ $msg->sender->name }}</div>
                                <div>{{ $msg->message }}</div>
                                @if($msg->attachment_path)
                                    <div class="mt-2 pt-1 border-t border-white/20">
                                        <a href="{{ Storage::url($msg->attachment_path) }}" target="_blank" class="underline font-medium">Attachment: {{ $msg->attachment_name }}</a>
                                    </div>
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1">{{ $msg->created_at->format('M d, h:i A') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500 text-center py-6">No messages in project chat thread yet.</p>
                    @endforelse
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <flux:input wire:model="chat_message" placeholder="Type message to supervisor..." class="flex-1" />
                    <flux:button wire:click="sendMessage" variant="primary" icon="paper-airplane" class="w-full sm:w-auto">Send</flux:button>
                </div>
            </div>
        @endif
    @endif
</div>
