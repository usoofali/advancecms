<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtQuestion extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (CbtQuestion $question) {
            $question->options()->delete();
        });
    }

    protected $fillable = [
        'cbt_exam_id',
        'question_text',
        'media_path',
        'type',
        'marks',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CbtExam::class, 'cbt_exam_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CbtOption::class);
    }
}
