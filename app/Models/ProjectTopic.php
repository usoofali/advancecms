<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_project_id',
        'topic_title',
        'description',
        'topic_order',
        'status',
        'feedback',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'topic_order' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentProject(): BelongsTo
    {
        return $this->belongsTo(StudentProject::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
