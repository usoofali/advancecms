<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'semester_id',
        'course_id',
        'title',
        'description',
        'duration_minutes',
        'start_date',
        'end_date',
        'test_type',
        'max_attempts',
        'is_published',
        'coin_reward_enabled',
        'randomize_questions',
        'randomize_options',
        'show_results',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_published' => 'boolean',
            'coin_reward_enabled' => 'boolean',
            'randomize_questions' => 'boolean',
            'randomize_options' => 'boolean',
            'show_results' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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

    public function questions(): HasMany
    {
        return $this->hasMany(CaQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CaAttempt::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(CaResult::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CaBlock::class);
    }
}
