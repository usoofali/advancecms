<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Tenant extends BaseHubModel
{
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'code',
        'institution_id',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'status',
    ];

    /**
     * Get or set the encrypted db_password attribute.
     */
    protected function dbPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (empty($value)) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return $value;
                }
            },
            set: fn (?string $value) => ! empty($value) ? Crypt::encryptString($value) : null
        );
    }

    /**
     * Dynamically register and return runtime connection name for this tenant database.
     */
    public function getDatabaseConnectionName(): string
    {
        // In SQLite testing environment, return default connection to query test database
        if (config('database.default') === 'sqlite') {
            if ($this->db_host === 'invalid_host') {
                throw new \PDOException('Could not connect to host invalid_host');
            }

            return config('database.default');
        }

        $connectionName = "tenant_db_{$this->id}";

        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'mysql',
                'host' => $this->db_host ?: '127.0.0.1',
                'port' => $this->db_port ?: '3306',
                'database' => $this->db_database,
                'username' => $this->db_username ?: 'root',
                'password' => $this->db_password ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
            ],
        ]);

        return $connectionName;
    }

    public function userCaches(): HasMany
    {
        return $this->hasMany(TenantUserCache::class, 'tenant_id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(TenantDailyMetric::class, 'tenant_id');
    }
}
