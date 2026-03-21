<?php

namespace App\Models;

use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'name',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
}
