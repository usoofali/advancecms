<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'ca_attempt_id',
        'ca_question_id',
        'ca_question_option_id',
        'is_correct',
        'marks_earned',
        'coins_earned',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'marks_earned' => 'decimal:2',
        ];
    }

    public function caAttempt(): BelongsTo
    {
        return $this->belongsTo(CaAttempt::class);
    }

    public function caQuestion(): BelongsTo
    {
        return $this->belongsTo(CaQuestion::class);
    }

    public function caQuestionOption(): BelongsTo
    {
        return $this->belongsTo(CaQuestionOption::class);
    }
}
