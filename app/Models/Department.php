<?php

namespace App\Models;

use App\Traits\HasRoleScopes;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, HasRoleScopes;

    protected $fillable = [
        'institution_id',
        'hod_id',
        'name',
        'faculty',
        'description',
        'max_session_units',
        'status',
        'grading_system_id',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'hod_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
    }
}
