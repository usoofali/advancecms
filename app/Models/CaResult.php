<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'ca_test_id',
        'student_id',
        'total_score',
        'normalized_score',
        'attempt_count',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'normalized_score' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function caTest(): BelongsTo
    {
        return $this->belongsTo(CaTest::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
