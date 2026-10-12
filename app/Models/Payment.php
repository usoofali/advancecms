<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'institution_id',
        'student_invoice_id',
        'applicant_id',
        'amount_paid',
        'payment_method',
        'payment_type',
        'reference',
        'gateway_order_no',
        'metadata',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Payment $payment) {
            if (empty(trim((string) $payment->reference))) {
                do {
                    $ref = 'PAY-'.strtoupper(Str::random(10));
                } while (static::where('reference', $ref)->exists());

                $payment->reference = $ref;
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function studentInvoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
