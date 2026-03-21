<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePayment extends Model
{
    protected $fillable = [
        'institution_id',
        'staff_id',
        'month',
        'year',
        'contacts_count',
        'allowance_rate',
        'total_amount',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
