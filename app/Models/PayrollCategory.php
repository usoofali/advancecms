<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\PayrollCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollCategory extends Model
{
    /** @use HasFactory<PayrollCategoryFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'name',
        'pay_type',
        'contact_formula',
        'default_rate',
        'is_default',
        'status',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(PayrollStaffProfile::class);
    }

    public function components(): MorphMany
    {
        return $this->morphMany(PayrollComponent::class, 'applicable');
    }

    /**
     * Human-readable labels for contact-based calculation formulas.
     *
     * @return array<string, string>
     */
    public static function contactFormulaLabels(): array
    {
        return [
            'contact_x_credit_unit' => 'Contacts × Course Credit Unit × Rate',
            'hour_x_credit_unit' => 'Session Hours × Course Credit Unit × Rate',
            'per_contact' => 'Per Contact Session × Rate',
            'per_hour' => 'Per Session Hour × Rate',
        ];
    }

    public function getContactFormulaLabelAttribute(): string
    {
        return self::contactFormulaLabels()[$this->contact_formula] ?? 'Fixed Monthly Salary';
    }

    public function getActivityModule(): string
    {
        return 'Payroll';
    }

    public function getActivityLogLabel(): string
    {
        return (string) $this->name;
    }
}
