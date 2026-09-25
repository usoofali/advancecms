<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserCache extends BaseHubModel
{
    protected $table = 'tenant_user_cache';

    protected $fillable = [
        'phone_number',
        'tenant_id',
        'external_user_id',
        'role',
        'institution_id',
        'metadata',
        'last_active_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
