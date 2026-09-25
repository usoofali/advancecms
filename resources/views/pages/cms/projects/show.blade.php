<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\StudentProject;
use App\Models\ProjectSubmission;
use App\Models\ProjectMessage;
use App\Models\Student;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public int $projectId;
    public string $activeTab = 'submissions'; // 'submissions', 'topics', 'chat', 'activity'

    // Chat Message
    public string $chat_message = '';
    public $chat_attachment = null;

    public function mount(StudentProject $project): void
    {
        $user = auth()->user();
        $student = Student::where('email', $user->email)->first();

        // Authorization check: User must be admin/coordinator/supervisor OR the owner student
        $isOwner = $student && $project->student_id === $student->id;
        $isSupervisor = $project->supervisor && $user->email === $project->supervisor->email;
        $hasPermission = $user->can('projects.view') || $user->can('projects.supervise') || $user->can('projects.view_dept');

        if (! $isOwner && ! $isSupervisor && ! $hasPermission) {
            abort(403, 'Unauthorized access to project workspace.');
        }

        $this->projectId = $project->id;
        $project->markMessagesAsReadForUser($user->id);
    }

    public function updatedActiveTab(string $tab): void
    {
        if ($tab === 'chat') {
            $project = StudentProject::find($this->projectId);
            $project?->markMessagesAsReadForUser(auth()->id());
        }
    }

    public function sendMessage(): void
    {
        $this->validate([
            'chat_message' => 'required|string',
            'chat_attachment' => 'nullable|file|max:10240',
        ]);

        $project = StudentProject::findOrFail($this->projectId);

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

        $project->logActivity('Message Sent', 'Sent a message in project chat.', auth()->id());

        $this->chat_message = '';
        $this->chat_attachment = null;
    }

    public function with(): array
    {
        $project = StudentProject::with([
            'student',
            'department',
            'program',
            'supervisor',
            'projectSession.stages',
            'approvedTopic',
            'topics',
            'submissions.stage',
            'messages.sender',
            'activities.user',
        ])->findOrFail($this->projectId);

        return compact('project');
    }
};
?>

<div>
    <!-- Workspace Header -->
    <div class="mb-6 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">{{ $project->student->first_name }} {{ $project->student->last_name }}</h2>
                    <flux:badge :variant="$project->overall_status === 'Completed' ? 'success' : 'warning'">
                        {{ $project->overall_status }}
                    </flux:badge>
                </div>
                <p class="text-xs font-mono text-gray-500 mt-1">Matric Number: {{ $project->student->matric_number }} | Department: {{ $project->department->name }} | Program: <span class="px-2 py-0.5 text-xs font-bold text-gray-800 dark:text-gray-200 bg-gray-100 dark:bg-zinc-800 rounded border border-gray-200 dark:border-gray-700 inline-block">{{ $project->program->acronym ?? $project->program->name }}</span></p>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-left md:text-right text-xs">
                    <span class="text-gray-500">Supervisor:</span>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $project->supervisor ? $project->supervisor->first_name . ' ' . $project->supervisor->last_name : 'Unassigned' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
            <span class="text-xs text-gray-500 font-medium">Approved Official Topic:</span>
            <p class="font-semibold text-gray-900 dark:text-white text-sm sm:text-base mt-0.5">
                {{ $project->approvedTopic?->topic_title ?: 'Topic Pending Approval' }}
            </p>
        </div>

        <!-- Progress Pipeline -->
        <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex justify-between text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                <span>Supervision Progress</span>
                <span>{{ $project->progress_percentage }}% Completed</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div class="bg-indigo-600 h-2.5 rounded-full transition-all" style="width: {{ $project->progress_percentage }}%"></div>
            </div>
        </div>
    </div>

    <!-- Workspace Tabs -->
    <div class="flex border-b border-gray-200 dark:border-gray-800 mb-6 gap-3 sm:gap-6 overflow-x-auto whitespace-nowrap scrollbar-none pb-1">
        <button wire:click="$set('activeTab', 'submissions')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'submissions' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Stage Submissions & History
        </button>
        <button wire:click="$set('activeTab', 'topics')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'topics' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Topic Proposals
        </button>
        <button wire:click="$set('activeTab', 'chat')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'chat' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Communication Hub
        </button>
        <button wire:click="$set('activeTab', 'activity')" class="pb-3 text-xs sm:text-sm font-semibold border-b-2 transition {{ $activeTab === 'activity' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Activity History Log
        </button>
    </div>

    <!-- Tab 1: Submissions -->
    @if($activeTab === 'submissions')
        <div class="space-y-4">
            @forelse($project->submissions as $sub)
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $sub->stage->title }}</span>
                            <flux:badge variant="subtle">Version {{ $sub->version_number }}</flux:badge>
                        </div>
                        <flux:badge :variant="$sub->status === 'Approved' ? 'success' : ($sub->status === 'Corrections Required' ? 'danger' : 'warning')">
                            {{ $sub->status }}
                        </flux:badge>
                    </div>

                    <div class="mt-2 text-xs text-gray-500 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <span>Submitted at: {{ $sub->created_at->format('M d, Y h:i A') }}</span>
                        @if($sub->file_path)
                            <a href="{{ Storage::url($sub->file_path) }}" target="_blank" class="text-indigo-600 font-medium hover:underline flex items-center gap-1">
                                <flux:icon.arrow-down-tray class="w-3.5 h-3.5" /> Download Student File
                            </a>
                        @endif
                    </div>

                    @if($sub->student_remarks)
                        <div class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                            <span class="font-semibold">Student Remarks:</span> {{ $sub->student_remarks }}
                        </div>
                    @endif

                    @if($sub->supervisor_feedback)
                        <div class="mt-3 p-3 bg-gray-50 dark:bg-zinc-800/50 border border-gray-200 dark:border-gray-700 rounded-lg text-xs">
                            <span class="font-bold text-gray-800 dark:text-gray-200">Supervisor Feedback:</span>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $sub->supervisor_feedback }}</p>

                            @if($sub->supervisor_file_path)
                                <div class="mt-2 pt-2 border-t text-xs">
                                    <a href="{{ Storage::url($sub->supervisor_file_path) }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline flex items-center gap-1">
                                        <flux:icon.paper-clip class="w-3.5 h-3.5" /> Download Supervisor Annotated Document
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 sm:p-12 text-center text-gray-500 text-sm">
                    No submissions uploaded for this project yet.
                </div>
            @endforelse
        </div>
    @endif

    <!-- Tab 2: Topics -->
    @if($activeTab === 'topics')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($project->topics as $tpc)
                <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Option {{ $tpc->topic_order }}</span>
                        <flux:badge :variant="$tpc->status === 'Approved' ? 'success' : ($tpc->status === 'Rejected' ? 'danger' : 'warning')">
                            {{ $tpc->status }}
                        </flux:badge>
                    </div>

                    <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base mt-2">{{ $tpc->topic_title }}</h4>
                    <p class="text-xs text-gray-500 mt-1">{{ $tpc->description }}</p>

                    @if($tpc->feedback)
                        <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-lg text-xs text-amber-900 dark:text-amber-200">
                            <span class="font-semibold">Review Feedback:</span> {{ $tpc->feedback }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-8 sm:p-12 text-center text-gray-500 text-sm">
                    No topic proposals submitted.
                </div>
            @endforelse
        </div>
    @endif

    <!-- Tab 3: Chat -->
    @if($activeTab === 'chat')
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Project Discussion Thread</h3>

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
                    <p class="text-xs text-gray-500 text-center py-6">No messages in chat thread yet.</p>
                @endforelse
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <flux:input wire:model="chat_message" placeholder="Type message..." class="flex-1" />
                <flux:button wire:click="sendMessage" variant="primary" icon="paper-airplane" class="w-full sm:w-auto">Send</flux:button>
            </div>
        </div>
    @endif

    <!-- Tab 4: Activity History -->
    @if($activeTab === 'activity')
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white mb-4">Project Activity Audit Log</h3>

            <div class="space-y-4">
                @forelse($project->activities as $act)
                    <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-zinc-800/40">
                        <div class="p-2 bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300 rounded-full text-xs shrink-0">
                            <flux:icon.clock class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="font-bold text-xs text-gray-900 dark:text-white">{{ $act->action }}</div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $act->details }}</p>
                            <span class="text-[10px] text-gray-400">{{ $act->created_at->format('M d, Y h:i A') }} by {{ $act->user?->name ?? 'System' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-500 text-center py-6">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
