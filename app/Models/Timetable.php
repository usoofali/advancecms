<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'semester_id',
        'department_id',
        'program_id',
        'level',
        'allocatable_type',
        'allocatable_id',
        'course_id',
        'user_id',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
    ];

    /**
     * Get the owning allocatable model (CourseAllocation or Course).
     */
    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courseAllocation(): BelongsTo
    {
        return $this->belongsTo(CourseAllocation::class, 'allocatable_id')
            ->where(fn () => $this->allocatable_type === CourseAllocation::class);
    }

    /**
     * Get resolved course model.
     */
    public function getResolvedCourseAttribute(): ?Course
    {
        if ($this->allocatable instanceof CourseAllocation) {
            return $this->allocatable->course;
        }

        if ($this->allocatable instanceof Course) {
            return $this->allocatable;
        }

        return $this->course;
    }

    /**
     * Get resolved lecturer user model.
     */
    public function getResolvedLecturerAttribute(): ?User
    {
        if ($this->allocatable instanceof CourseAllocation && $this->allocatable->user) {
            return $this->allocatable->user;
        }

        return $this->lecturer;
    }

    /**
     * Scope for a student based on their registered courses or program & level.
     */
    public function scopeForStudent($query, User $user)
    {
        $student = $user->student;
        if (! $student) {
            return $query->whereRaw('1 = 0');
        }

        // Get registered course IDs for active session/semester
        $registeredCourseIds = CourseRegistration::where('student_id', $student->id)
            ->pluck('course_id')
            ->toArray();

        return $query->where(function ($q) use ($student, $registeredCourseIds) {
            if (! empty($registeredCourseIds)) {
                $q->whereIn('course_id', $registeredCourseIds);
            }

            $q->orWhere(function ($sub) use ($student) {
                $sub->where('program_id', $student->program_id)
                    ->where('level', (string) $student->level);
            });
        });
    }

    /**
     * Scope for a lecturer based on assigned course allocations or user_id.
     */
    public function scopeForLecturer($query, User $user)
    {
        $allocationIds = CourseAllocation::where('user_id', $user->id)->pluck('id')->toArray();

        return $query->where(function ($q) use ($user, $allocationIds) {
            $q->where('user_id', $user->id);
            if (! empty($allocationIds)) {
                $q->orWhere(function ($sub) use ($allocationIds) {
                    $sub->where('allocatable_type', CourseAllocation::class)
                        ->whereIn('allocatable_id', $allocationIds);
                });
            }
        });
    }
}
