<?php

namespace App\Console\Commands;

use App\Models\Hub\Tenant;
use App\Models\Hub\TenantDailyMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarvestMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:harvest-metrics {--tenant= : Specific tenant code to harvest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Harvest daily metrics from all active tenant Spoke nodes into the central hub database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantCode = $this->option('tenant');

        $query = Tenant::where('status', 'active');
        if ($tenantCode) {
            $query->where('code', $tenantCode);
        }

        try {
            $tenants = $query->get();
        } catch (\Throwable $e) {
            $this->error('Failed to query tenants table on hub_mysql: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->info('No active tenants found for harvesting.');

            return Command::SUCCESS;
        }

        $this->info(sprintf('Starting analytics harvest for %d tenant(s)...', $tenants->count()));
        $today = today()->toDateString();
        $successCount = 0;

        foreach ($tenants as $tenant) {
            try {
                $conn = $tenant->getDatabaseConnectionName();
                $instId = $tenant->institution_id;

                $totalStudentsQuery = DB::connection($conn)->table('students');
                if ($instId) {
                    $totalStudentsQuery->where('institution_id', $instId);
                }
                $totalStudents = $totalStudentsQuery->count();

                $activeStudentsQuery = DB::connection($conn)->table('students')
                    ->where(function ($query) {
                        $query->whereIn('status', ['active', 'Active'])
                            ->orWhereNull('status');
                    });
                if ($instId) {
                    $activeStudentsQuery->where('institution_id', $instId);
                }
                $activeStudents = $activeStudentsQuery->count();

                $totalStaffQuery = DB::connection($conn)->table('staff');
                if ($instId) {
                    $totalStaffQuery->where('institution_id', $instId);
                }
                $totalStaff = $totalStaffQuery->count();

                $paymentsQuery = DB::connection($conn)->table('payments')
                    ->whereIn('status', ['successful', 'paid', 'completed', 'success']);
                if ($instId) {
                    $paymentsQuery->where('institution_id', $instId);
                }
                $feesCollected = (float) $paymentsQuery->sum('amount_paid');

                if ($feesCollected === 0.0) {
                    $allPaymentsQuery = DB::connection($conn)->table('payments');
                    if ($instId) {
                        $allPaymentsQuery->where('institution_id', $instId);
                    }
                    $feesCollected = (float) $allPaymentsQuery->sum('amount_paid');
                }

                TenantDailyMetric::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'metric_date' => $today,
                    ],
                    [
                        'total_students' => $totalStudents,
                        'active_students' => $activeStudents,
                        'total_staff' => $totalStaff,
                        'fees_collected' => round($feesCollected, 2),
                        'raw_payload' => [
                            'mode' => 'direct_db',
                            'database' => $tenant->db_database,
                            'harvested_at' => now()->toIso8601String(),
                        ],
                    ]
                );

                $successCount++;
                $this->info(sprintf('[SUCCESS] Harvested direct DB metrics for tenant: %s (%s)', $tenant->name, $tenant->code));
            } catch (\Throwable $e) {
                $this->handleTenantHarvestFailure($tenant, $today, sprintf('Direct DB connection error (%s): %s', $tenant->db_database, $e->getMessage()));
            }
        }

        $this->info(sprintf('Harvest complete! Successfully updated %d of %d tenant(s).', $successCount, $tenants->count()));

        return Command::SUCCESS;
    }

    /**
     * Record soft failure details in tenant_daily_metrics without interrupting execution.
     */
    protected function handleTenantHarvestFailure(Tenant $tenant, string $metricDate, string $errorMessage): void
    {
        Log::warning(sprintf('Analytics harvest failed for tenant [%s]: %s', $tenant->code, $errorMessage));
        $this->warn(sprintf('[WARN] Failed to harvest tenant [%s]: %s', $tenant->code, $errorMessage));

        try {
            TenantDailyMetric::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'metric_date' => $metricDate,
                ],
                [
                    'raw_payload' => [
                        'status' => 'failed',
                        'error' => $errorMessage,
                        'failed_at' => now()->toIso8601String(),
                    ],
                ]
            );
        } catch (\Throwable) {
            // Ignore DB log errors to ensure command execution continues
        }
    }
}
