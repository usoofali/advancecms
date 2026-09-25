<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSession extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'title',
        'coordinator_id',
        'start_date',
        'end_date',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'coordinator_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectSessionStage::class)->orderBy('stage_order');
    }

    public function studentProjects(): HasMany
    {
        return $this->hasMany(StudentProject::class);
    }

    public function getActivityModule(): string
    {
        return 'Project Sessions';
    }
}
