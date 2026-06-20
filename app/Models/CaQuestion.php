<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'ca_test_id',
        'text',
        'image_path',
        'coin_reward',
        'marks',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
        ];
    }

    public function caTest(): BelongsTo
    {
        return $this->belongsTo(CaTest::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(CaQuestionOption::class);
    }
}
