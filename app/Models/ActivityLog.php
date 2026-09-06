<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'user_id',
        'subject_type',
        'subject_id',
        'subject_label',
        'action',
        'module',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForInstitution(Builder $query, ?int $institutionId): Builder
    {
        if (! $institutionId) {
            return $query;
        }

        return $query->where('institution_id', $institutionId);
    }

    public function scopeForModule(Builder $query, ?string $module): Builder
    {
        if (empty($module)) {
            return $query;
        }

        return $query->where('module', $module);
    }

    public function scopeForAction(Builder $query, ?string $action): Builder
    {
        if (empty($action)) {
            return $query;
        }

        return $query->where('action', $action);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (! $userId) {
            return $query;
        }

        return $query->where('user_id', $userId);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $term = '%'.$search.'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'like', $term)
                ->orWhere('subject_label', 'like', $term)
                ->orWhere('action', 'like', $term)
                ->orWhere('module', 'like', $term)
                ->orWhere('ip_address', 'like', $term)
                ->orWhere('user_agent', 'like', $term)
                ->orWhereHas('user', function (Builder $uq) use ($term) {
                    $uq->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
        });
    }

    /**
     * Get parsed browser name from user agent.
     */
    public function getBrowserAttribute(): string
    {
        $ua = $this->user_agent ?? '';
        if (empty($ua)) {
            return 'Unknown Browser';
        }

        if (str_contains($ua, 'PostmanRuntime')) {
            return 'Postman';
        }
        if (str_contains($ua, 'curl')) {
            return 'cURL';
        }
        if (str_contains($ua, 'Edg/') || str_contains($ua, 'Edge/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            return 'Safari';
        }
        if (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/')) {
            return 'Internet Explorer';
        }

        return 'Web Browser';
    }

    /**
     * Get parsed operating system name from user agent.
     */
    public function getDeviceOsAttribute(): string
    {
        $ua = $this->user_agent ?? '';
        if (empty($ua)) {
            return 'Unknown OS';
        }

        if (str_contains($ua, 'iPhone')) {
            return 'iOS';
        }
        if (str_contains($ua, 'iPad')) {
            return 'iPadOS';
        }
        if (str_contains($ua, 'Android')) {
            return 'Android';
        }
        if (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X')) {
            return 'macOS';
        }
        if (str_contains($ua, 'Windows')) {
            return 'Windows';
        }
        if (str_contains($ua, 'Linux')) {
            return 'Linux';
        }
        if (str_contains($ua, 'CrOS')) {
            return 'Chrome OS';
        }

        return 'Other System';
    }

    /**
     * Get parsed device type (Desktop, Mobile, Tablet, API Client).
     */
    public function getDeviceTypeAttribute(): string
    {
        $ua = $this->user_agent ?? '';
        if (empty($ua)) {
            return 'Unknown';
        }

        if (str_contains($ua, 'PostmanRuntime') || str_contains($ua, 'curl')) {
            return 'API Client';
        }
        if (str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile'))) {
            return 'Tablet';
        }
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'Mobile') || str_contains($ua, 'Windows Phone')) {
            return 'Mobile';
        }
        if (str_contains($ua, 'Macintosh') || str_contains($ua, 'Windows') || str_contains($ua, 'Linux') || str_contains($ua, 'CrOS')) {
            return 'Desktop';
        }

        return 'Device';
    }

    /**
     * Get formatted summary of device, browser, and OS.
     */
    public function getDeviceSummaryAttribute(): string
    {
        if (empty($this->user_agent)) {
            return 'Unknown Device';
        }

        $browser = $this->browser;
        $os = $this->device_os;
        $type = $this->device_type;

        if ($type === 'API Client') {
            return "{$browser} (API)";
        }

        return "{$browser} on {$os} ({$type})";
    }
}
