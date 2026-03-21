<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
