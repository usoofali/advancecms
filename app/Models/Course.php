<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'program_id',
        'department_id',
        'course_code',
        'title',
        'credit_unit',
        'course_type',
        'level',
        'semester',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_courses')
            ->withPivot(['level', 'semester', 'status'])
            ->withTimestamps();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * Get the staff allocations for this course.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CourseAllocation::class);
    }
}
