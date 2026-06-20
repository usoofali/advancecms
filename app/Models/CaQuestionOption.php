<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'ca_question_id',
        'text',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function caQuestion(): BelongsTo
    {
        return $this->belongsTo(CaQuestion::class);
    }
}
