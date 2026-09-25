<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_project_id',
        'user_id',
        'action',
        'details',
    ];

    public function studentProject(): BelongsTo
    {
        return $this->belongsTo(StudentProject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
