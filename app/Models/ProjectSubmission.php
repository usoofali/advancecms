<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_project_id',
        'stage_id',
        'version_number',
        'student_remarks',
        'file_path',
        'file_original_name',
        'status',
        'supervisor_feedback',
        'supervisor_file_path',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentProject(): BelongsTo
    {
        return $this->belongsTo(StudentProject::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectSessionStage::class, 'stage_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
