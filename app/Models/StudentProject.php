<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProject extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'project_session_id',
        'student_id',
        'department_id',
        'program_id',
        'supervisor_id',
        'approved_topic_id',
        'current_stage_id',
        'overall_status',
        'progress_percentage',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function projectSession(): BelongsTo
    {
        return $this->belongsTo(ProjectSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervisor_id');
    }

    public function approvedTopic(): BelongsTo
    {
        return $this->belongsTo(ProjectTopic::class, 'approved_topic_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(ProjectSessionStage::class, 'current_stage_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(ProjectTopic::class)->orderBy('topic_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class)->orderBy('version_number', 'desc');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->latest();
    }

    /**
     * Recalculates progress percentage based on approved stages.
     */
    public function updateProgress(): void
    {
        $session = $this->projectSession;
        if (! $session) {
            return;
        }

        $totalStages = $session->stages()->count();
        if ($totalStages === 0) {
            $this->progress_percentage = 0;
            $this->save();

            return;
        }

        $approvedStagesCount = $this->submissions()
            ->where('status', 'Approved')
            ->distinct('stage_id')
            ->count('stage_id');

        $percentage = (int) round(($approvedStagesCount / $totalStages) * 100);
        $this->progress_percentage = min(100, max(0, $percentage));

        if ($percentage >= 100 && $this->overall_status !== 'Completed') {
            $this->overall_status = 'Completed';
            $this->completed_at = now();
        }

        $this->save();
    }

    /**
     * Marks unread messages sent by other users as read.
     */
    public function markMessagesAsReadForUser(int $userId): void
    {
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Gets unread message count for a given user.
     */
    public function unreadMessagesCountForUser(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Registers a student for a project session if eligible.
     */
    public static function selfRegister(Student $student, ProjectSession $session): ?self
    {
        $eligibility = static::checkEligibility($student, $session->academicSession);
        if (! $eligibility['eligible']) {
            return null;
        }

        $firstStage = $session->stages()->orderBy('stage_order')->first();

        $project = static::firstOrCreate(
            [
                'project_session_id' => $session->id,
                'student_id' => $student->id,
            ],
            [
                'institution_id' => $student->institution_id,
                'department_id' => $student->department?->id ?? $student->program?->department_id,
                'program_id' => $student->program_id,
                'current_stage_id' => $firstStage?->id,
                'overall_status' => 'Topic Pending',
                'progress_percentage' => 0,
            ]
        );

        if ($project->wasRecentlyCreated) {
            $project->logActivity('Registered for Project Session', 'Student registered for project session: '.$session->title, auth()->id());
        }

        return $project;
    }

    /**
     * Checks if a given student is eligible for project registration in an academic session.
     */
    public static function checkEligibility(Student $student, AcademicSession $session): array
    {
        $program = $student->program;
        if (! $program) {
            return [
                'eligible' => false,
                'reason' => 'Student is not enrolled in a degree program.',
                'current_level' => null,
                'required_level' => null,
            ];
        }

        $currentLevel = $student->currentLevel($session);
        $durationYears = (int) ($program->duration_years ?? 0);

        if ($durationYears <= 0) {
            return [
                'eligible' => false,
                'reason' => 'Program duration is not defined.',
                'current_level' => $currentLevel,
                'required_level' => null,
            ];
        }

        $requiredFinalLevel = $durationYears * 100;
        $isEligible = ($currentLevel >= $requiredFinalLevel);

        return [
            'eligible' => $isEligible,
            'reason' => $isEligible
                ? 'Eligible: Final year student ('.$currentLevel.' Level).'
                : 'Ineligible: Student is at '.$currentLevel.' Level (Required: '.$requiredFinalLevel.' Level for '.$durationYears.'-year program).',
            'current_level' => $currentLevel,
            'required_level' => $requiredFinalLevel,
        ];
    }

    public function logActivity(string $action, ?string $details = null, ?int $userId = null): void
    {
        $this->activities()->create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'details' => $details,
        ]);
    }

    public function getActivityModule(): string
    {
        return 'Student Projects';
    }
}
