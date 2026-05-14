<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtExam extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'institution_id',
        'course_id',
        'academic_session_id',
        'semester_id',
        'title',
        'duration_minutes',
        'total_questions',
        'pass_mark',
        'randomize_questions',
        'randomize_options',
        'status',
        'exam_date',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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

    public function questions(): HasMany
    {
        return $this->hasMany(CbtQuestion::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CbtResultStaging::class);
    }
}
