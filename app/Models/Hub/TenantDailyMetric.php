<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDailyMetric extends BaseHubModel
{
    protected $table = 'tenant_daily_metrics';

    protected $fillable = [
        'tenant_id',
        'metric_date',
        'total_students',
        'active_students',
        'total_staff',
        'fees_collected',
        'raw_payload',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'fees_collected' => 'decimal:2',
        'raw_payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
