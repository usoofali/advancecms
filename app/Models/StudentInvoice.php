<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentInvoice extends Model
{
    protected $fillable = [
        'institution_id',
        'student_id',
        'applicant_id',
        'invoice_id',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
