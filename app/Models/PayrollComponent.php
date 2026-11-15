<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\PayrollComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayrollComponent extends Model
{
    /** @use HasFactory<PayrollComponentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'institution_id',
        'name',
        'type',
        'calculation_type',
        'amount_or_rate',
        'applicable_type',
        'applicable_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_or_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function applicable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getScopeLabelAttribute(): string
    {
        if (! $this->applicable_type || ! $this->applicable_id) {
            return 'All Staff';
        }

        $target = $this->applicable;
        if (! $target) {
            return class_basename($this->applicable_type);
        }

        if ($target instanceof Staff) {
            return 'Staff: '.trim("{$target->first_name} {$target->last_name}");
        }

        return class_basename($this->applicable_type).': '.($target->name ?? "#{$this->applicable_id}");
    }

    public function getActivityModule(): string
    {
        return 'Payroll';
    }

    public function getActivityLogLabel(): string
    {
        return "{$this->name} ({$this->type})";
    }
}
