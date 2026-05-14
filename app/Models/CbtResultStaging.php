<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtResultStaging extends Model
{
    use HasFactory;

    protected $table = 'cbt_results_staging';

    protected $fillable = [
        'cbt_exam_id',
        'student_id',
        'attempt_number',
        'attempt_type',
        'score_raw',
        'score_percent',
        'responses',
        'submission_token',
        'status',
        'synced_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'synced_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CbtExam::class, 'cbt_exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
