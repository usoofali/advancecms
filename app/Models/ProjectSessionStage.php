<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectSessionStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_session_id',
        'title',
        'stage_order',
        'description',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'stage_order' => 'integer',
        ];
    }

    public function projectSession(): BelongsTo
    {
        return $this->belongsTo(ProjectSession::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class, 'stage_id');
    }
}
