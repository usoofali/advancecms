<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'department_id',
        'name',
        'acronym',
        'duration_years',
        'award_type',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'program_courses')
            ->withPivot(['level', 'semester', 'status'])
            ->withTimestamps();
    }
}
