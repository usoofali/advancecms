<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'institution_id',
        'payment_id',
        'receipt_number',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
