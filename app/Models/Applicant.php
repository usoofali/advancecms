<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Applicant extends Model
{
    use Notifiable;

    protected $fillable = [
        'application_number',
        'full_name',
        'email',
        'phone',
        'institution_id',
        'program_id',
        'application_form_id',
        'payment_status',
        'admission_status',
        'enrolled_at',
        'gateway_reference',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function applicationForm(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(ApplicantCredential::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function studentInvoices(): HasMany
    {
        return $this->hasMany(StudentInvoice::class);
    }
}
