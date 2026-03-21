<?php

namespace App\Models;

use Database\Factories\CourseRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRegistration extends Model
{
    /** @use HasFactory<CourseRegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'student_id',
        'course_id',
        'academic_session_id',
        'semester_id',
        'status',
        'is_carryover',
    ];

    protected function casts(): array
    {
        return [
            'is_carryover' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
