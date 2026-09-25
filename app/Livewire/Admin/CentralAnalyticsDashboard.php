<?php

namespace App\Livewire\Admin;

use App\Models\Hub\Tenant;
use App\Models\Hub\TenantDailyMetric;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.hub')]
class CentralAnalyticsDashboard extends Component
{
    use WithPagination;

    public string $selectedTenantId = 'all';

    public string $dateRange = '30_days';

    public string $search = '';

    // Tenant Modal state
    public bool $showTenantModal = false;

    public ?int $editingTenantId = null;

    public string $tenantName = '';

    public string $tenantCode = '';

    public string $tenantDbHost = '127.0.0.1';

    public string $tenantDbPort = '3306';

    public ?string $tenantDbDatabase = null;

    public ?string $tenantDbUsername = null;

    public ?string $tenantDbPassword = null;

    public string $tenantStatus = 'active';

    public ?string $feedbackMessage = null;

    protected function rules(): array
    {
        return [
            'tenantName' => 'required|string|max:255',
            'tenantCode' => 'required|string|max:50',
            'tenantDbHost' => 'required|string|max:255',
            'tenantDbPort' => 'required|string|max:10',
            'tenantDbDatabase' => 'required|string|max:255',
            'tenantDbUsername' => 'required|string|max:255',
            'tenantDbPassword' => 'nullable|string',
            'tenantStatus' => 'required|in:active,suspended,maintenance',
        ];
    }

    public function mount(): void
    {
        //
    }

    public function logout(): void
    {
        session()->forget('hub_authenticated');
        $this->redirectRoute('hub.login');
    }

    public function triggerHarvest(): void
    {
        try {
            Artisan::call('analytics:harvest-metrics');
            $this->feedbackMessage = 'Metrics harvest process executed successfully!';
        } catch (\Throwable $e) {
            $this->feedbackMessage = 'Harvest failed: '.$e->getMessage();
        }
    }

    public function openNewTenantModal(): void
    {
        $this->reset([
            'editingTenantId', 'tenantName', 'tenantCode',
            'tenantDbHost', 'tenantDbPort', 'tenantDbDatabase',
            'tenantDbUsername', 'tenantDbPassword', 'tenantStatus',
        ]);
        $this->tenantDbHost = '127.0.0.1';
        $this->tenantDbPort = '3306';
        $this->tenantStatus = 'active';
        $this->showTenantModal = true;
    }

    public function editTenant(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        $this->editingTenantId = $tenant->id;
        $this->tenantName = $tenant->name;
        $this->tenantCode = $tenant->code;
        $this->tenantDbHost = $tenant->db_host ?? '127.0.0.1';
        $this->tenantDbPort = $tenant->db_port ?? '3306';
        $this->tenantDbDatabase = $tenant->db_database;
        $this->tenantDbUsername = $tenant->db_username;
        $this->tenantDbPassword = $tenant->db_password ?? '';
        $this->tenantStatus = $tenant->status;

        $this->showTenantModal = true;
    }

    public function saveTenant(): void
    {
        $this->validate();

        Tenant::updateOrCreate(
            ['id' => $this->editingTenantId],
            [
                'name' => $this->tenantName,
                'code' => strtoupper($this->tenantCode),
                'db_host' => $this->tenantDbHost,
                'db_port' => $this->tenantDbPort,
                'db_database' => $this->tenantDbDatabase,
                'db_username' => $this->tenantDbUsername,
                'db_password' => $this->tenantDbPassword,
                'status' => $this->tenantStatus,
            ]
        );

        $this->showTenantModal = false;
        $this->feedbackMessage = 'Tenant information saved successfully.';
    }

    public function render(): View
    {
        $tenantsQuery = Tenant::query();

        if (! empty($this->search)) {
            $tenantsQuery->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        $tenants = $tenantsQuery->get();

        // Calculate aggregated metrics
        $latestMetricsQuery = TenantDailyMetric::query();

        if ($this->selectedTenantId !== 'all') {
            $latestMetricsQuery->where('tenant_id', $this->selectedTenantId);
        }

        $totals = [
            'total_students' => (int) $latestMetricsQuery->sum('total_students'),
            'active_students' => (int) $latestMetricsQuery->sum('active_students'),
            'total_staff' => (int) $latestMetricsQuery->sum('total_staff'),
            'fees_collected' => (float) $latestMetricsQuery->sum('fees_collected'),
        ];

        $recentMetrics = TenantDailyMetric::with('tenant')
            ->orderBy('metric_date', 'desc')
            ->paginate(15);

        return view('livewire.admin.central-analytics-dashboard', [
            'tenants' => $tenants,
            'totals' => $totals,
            'recentMetrics' => $recentMetrics,
        ]);
    }
}
