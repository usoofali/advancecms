<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] #[Title('Activity Audit Logs')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $module = '';

    public string $action = '';

    public string $date_from = '';

    public string $date_to = '';

    public string $user_id = '';

    public bool $showDetailsModal = false;

    public bool $showClearModal = false;

    public string $clearPeriod = '30_days';

    public ?array $selectedLogDetails = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModule(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'module', 'action', 'date_from', 'date_to', 'user_id']);
        $this->resetPage();
    }

    public function mount(): void
    {
        Gate::authorize('activity_logs.view');
    }

    public function viewDetails(int $id): void
    {
        Gate::authorize('activity_logs.view');

        $instId = auth()->user()->institution_id;
        $log = ActivityLog::with(['user', 'institution'])
            ->when($instId, fn ($q) => $q->where('institution_id', $instId))
            ->findOrFail($id);

        $this->selectedLogDetails = [
            'id' => $log->id,
            'user' => $log->user?->name ?? 'System / Anonymous',
            'user_email' => $log->user?->email ?? 'N/A',
            'module' => $log->module,
            'action' => $log->action,
            'description' => $log->description,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'subject_label' => $log->subject_label,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'browser' => $log->browser,
            'device_os' => $log->device_os,
            'device_type' => $log->device_type,
            'device_summary' => $log->device_summary,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s T') ?? '',
        ];

        $this->showDetailsModal = true;
    }

    public function exportCsv(): StreamedResponse
    {
        Gate::authorize('activity_logs.export');

        $instId = auth()->user()->institution_id;
        $logs = ActivityLog::with('user')
            ->forInstitution($instId)
            ->forModule($this->module)
            ->forAction($this->action)
            ->when($this->user_id, fn ($q) => $q->where('user_id', $this->user_id))
            ->when($this->date_from, fn ($q) => $q->whereDate('created_at', '>=', $this->date_from))
            ->when($this->date_to, fn ($q) => $q->whereDate('created_at', '<=', $this->date_to))
            ->search($this->search)
            ->latest()
            ->get();

        $filename = 'activity_logs_'.now()->format('YmdHis').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Date/Time', 'User Name', 'User Email', 'Module', 'Action', 'Description', 'Subject Label', 'Device & Browser', 'IP Address', 'User Agent']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? 'N/A',
                    $log->module,
                    strtoupper($log->action),
                    $log->description,
                    $log->subject_label ?? 'N/A',
                    $log->device_summary,
                    $log->ip_address ?? 'N/A',
                    $log->user_agent ?? 'N/A',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function purgeLogs(): void
    {
        Gate::authorize('activity_logs.clear');

        $instId = auth()->user()->institution_id;
        $query = ActivityLog::forInstitution($instId);

        if ($this->clearPeriod === '30_days') {
            $query->where('created_at', '<', now()->subDays(30));
            $periodLabel = 'older than 30 days';
        } elseif ($this->clearPeriod === '90_days') {
            $query->where('created_at', '<', now()->subDays(90));
            $periodLabel = 'older than 90 days';
        } elseif ($this->clearPeriod === '180_days') {
            $query->where('created_at', '<', now()->subDays(180));
            $periodLabel = 'older than 180 days';
        } elseif ($this->clearPeriod === 'all') {
            $periodLabel = 'all historical logs';
        } else {
            return;
        }

        $count = $query->count();
        if ($count === 0) {
            Flux::toast(__('No activity log records found matching the selected timeframe.'), variant: 'warning');
            $this->showClearModal = false;

            return;
        }

        $query->delete();

        ActivityLogger::log(
            action: 'deleted',
            module: 'System',
            description: "Purged {$count} activity log records ({$periodLabel}).",
            user: auth()->user(),
            institutionId: $instId
        );

        $this->showClearModal = false;
        $this->resetPage();

        Flux::toast("Successfully purged {$count} activity log records ({$periodLabel}).", variant: 'success');
    }

    public function with(): array
    {
        $instId = auth()->user()->institution_id;

        $query = ActivityLog::with('user')
            ->forInstitution($instId)
            ->forModule($this->module)
            ->forAction($this->action)
            ->when($this->user_id, fn ($q) => $q->where('user_id', $this->user_id))
            ->when($this->date_from, fn ($q) => $q->whereDate('created_at', '>=', $this->date_from))
            ->when($this->date_to, fn ($q) => $q->whereDate('created_at', '<=', $this->date_to))
            ->search($this->search)
            ->latest();

        $statsQuery = ActivityLog::forInstitution($instId);

        $topModule = $statsQuery->clone()
            ->select('module', DB::raw('count(*) as count'))
            ->groupBy('module')
            ->orderByDesc('count')
            ->first();

        return [
            'logs' => $query->paginate(15),
            'stats' => [
                'total' => $statsQuery->clone()->count(),
                'today' => $statsQuery->clone()->whereDate('created_at', now()->today())->count(),
                'active_users_today' => $statsQuery->clone()->whereDate('created_at', now()->today())->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'top_module' => $topModule ? $topModule->module.' ('.$topModule->count.')' : 'N/A',
            ],
            'modules' => ActivityLog::forInstitution($instId)->distinct()->pluck('module')->filter()->sort()->values(),
            'actions' => ActivityLog::forInstitution($instId)->distinct()->pluck('action')->filter()->sort()->values(),
            'users' => User::when($instId, fn ($q) => $q->where('institution_id', $instId))->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }
}; ?>

<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Activity Audit Logs') }}</flux:heading>
            <flux:subheading>{{ __('Monitor, inspect, and audit critical user and system activities across the platform.') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @can('activity_logs.export')
                <flux:button icon="arrow-down-tray" variant="subtle" wire:click="exportCsv">
                    {{ __('Export CSV Audit Trail') }}
                </flux:button>
            @endcan
            @can('activity_logs.clear')
                <flux:button icon="trash" variant="danger" wire:click="$set('showClearModal', true)">
                    {{ __('Clear Audit Logs') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-50 dark:bg-blue-950/50 rounded-xl text-blue-600 dark:text-blue-400">
                    <flux:icon icon="clock" class="size-6" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-wider font-bold text-zinc-500">{{ __('Total Logged Activities') }}</flux:text>
                    <div class="text-2xl font-black text-zinc-900 dark:text-white mt-0.5">{{ number_format($stats['total']) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400">
                    <flux:icon icon="check-circle" class="size-6" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-wider font-bold text-zinc-500">{{ __('Activities Today') }}</flux:text>
                    <div class="text-2xl font-black text-zinc-900 dark:text-white mt-0.5">{{ number_format($stats['today']) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-purple-50 dark:bg-purple-950/50 rounded-xl text-purple-600 dark:text-purple-400">
                    <flux:icon icon="users" class="size-6" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-wider font-bold text-zinc-500">{{ __('Active Users Today') }}</flux:text>
                    <div class="text-2xl font-black text-zinc-900 dark:text-white mt-0.5">{{ number_format($stats['active_users_today']) }}</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400">
                    <flux:icon icon="chart-bar" class="size-6" />
                </div>
                <div>
                    <flux:text size="xs" class="uppercase tracking-wider font-bold text-zinc-500">{{ __('Top Active Module') }}</flux:text>
                    <div class="text-base font-bold text-zinc-900 dark:text-white mt-0.5 truncate max-w-[160px]">{{ $stats['top_module'] }}</div>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Filter Bar --}}
    <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <flux:input icon="magnifying-glass" placeholder="{{ __('Search log, user, description...') }}" wire:model.live.debounce.300ms="search" />
            </div>

            <div>
                <flux:select wire:model.live="module">
                    <option value="">{{ __('All Modules') }}</option>
                    @foreach($modules as $mod)
                        <option value="{{ $mod }}">{{ $mod }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="action">
                    <option value="">{{ __('All Actions') }}</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}">{{ strtoupper($act) }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:select wire:model.live="user_id">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-2">
                <flux:button variant="ghost" class="w-full" wire:click="resetFilters" icon="x-mark">
                    {{ __('Reset Filters') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Logs Table --}}
    <flux:card class="p-4 sm:p-6 overflow-hidden border border-zinc-200 dark:border-zinc-800 space-y-4">
        <div class="overflow-x-auto">
            <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column class="pl-2 sm:pl-4">{{ __('Performer User') }}</flux:table.column>
                <flux:table.column>{{ __('Activity & Time') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="pl-2 sm:pl-4">
                            <div class="flex items-center gap-2">
                                <flux:avatar :name="$log->user?->name ?? 'System'" size="xs" />
                                <div>
                                    <div class="text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $log->user?->name ?? 'System / Automated' }}
                                    </div>
                                    @if($log->user)
                                        <div class="text-[10px] text-zinc-400">{{ $log->user->email }}</div>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5 mb-1">
                                <flux:badge variant="subtle" size="sm">{{ $log->module }}</flux:badge>
                                @php
                                    $actionColor = match(strtolower($log->action)) {
                                        'created', 'approved' => 'success',
                                        'updated', 'imported', 'exported' => 'sky',
                                        'deleted', 'rejected' => 'red',
                                        'login' => 'purple',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge :color="$actionColor" size="sm" class="uppercase text-[10px] font-bold">
                                    {{ $log->action }}
                                </flux:badge>
                            </div>
                            <div class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400 flex items-center gap-1">
                                <flux:icon icon="clock" class="size-3 text-zinc-400 inline" />
                                <span>{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                                <span class="text-[10px] text-zinc-400">({{ $log->created_at?->diffForHumans() }})</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="max-w-md">
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200 line-clamp-2" title="{{ $log->description }}">
                                {{ $log->description }}
                            </div>
                            @if($log->subject_label)
                                <div class="text-[10px] font-mono text-zinc-400 mt-0.5 truncate">
                                    Target: {{ $log->subject_label }}
                                </div>
                            @endif
                            <div class="text-[10px] text-zinc-400 mt-1 flex items-center gap-1.5 flex-wrap">
                                <flux:icon icon="computer-desktop" class="size-3 inline text-zinc-400 shrink-0" />
                                <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $log->device_summary }}</span>
                                @if($log->ip_address)
                                    <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                    <span class="font-mono text-zinc-400">IP: {{ $log->ip_address }}</span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button variant="ghost" size="xs" icon="eye" wire:click="viewDetails({{ $log->id }})">
                                {{ __('Inspect') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-16 text-zinc-500">
                            <flux:icon icon="clock" class="size-12 mx-auto mb-3 opacity-20" />
                            <p>{{ __('No activity log records found matching your filters.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>
    </flux:card>

    {{-- Details Inspection Modal --}}
    <flux:modal wire:model="showDetailsModal" class="w-full max-w-3xl">
        @if($selectedLogDetails)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Activity Detail Inspector') }}</flux:heading>
                    <flux:subheading>{{ __('Detailed parameters and change attributes for Log #') }}{{ $selectedLogDetails['id'] }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Module') }}:</span>
                        <div class="mt-0.5">
                            <flux:badge variant="subtle" size="sm">{{ $selectedLogDetails['module'] }}</flux:badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Action') }}:</span>
                        @php
                            $modalActionColor = match(strtolower($selectedLogDetails['action'])) {
                                'created', 'approved' => 'success',
                                'updated', 'imported', 'exported' => 'sky',
                                'deleted', 'rejected' => 'red',
                                'login' => 'purple',
                                default => 'zinc',
                            };
                        @endphp
                        <div class="mt-0.5">
                            <flux:badge :color="$modalActionColor" size="sm" class="uppercase font-bold">
                                {{ $selectedLogDetails['action'] }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Timestamp') }}:</span>
                        <p class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $selectedLogDetails['created_at'] }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Performer User') }}:</span>
                        <p class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $selectedLogDetails['user'] }}</p>
                        <p class="text-[10px] text-zinc-400">{{ $selectedLogDetails['user_email'] }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('IP Address') }}:</span>
                        <p class="font-mono text-zinc-900 dark:text-white mt-0.5">{{ $selectedLogDetails['ip_address'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Device & Browser') }}:</span>
                        <p class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $selectedLogDetails['device_summary'] ?? 'N/A' }}</p>
                    </div>
                    <div class="sm:col-span-2 md:col-span-3">
                        <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Summary Description') }}:</span>
                        <p class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $selectedLogDetails['description'] }}</p>
                    </div>
                    @if($selectedLogDetails['subject_label'])
                        <div class="sm:col-span-2 md:col-span-3">
                            <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Target Entity') }}:</span>
                            <p class="font-mono text-zinc-700 dark:text-zinc-300 mt-0.5 break-all">{{ $selectedLogDetails['subject_type'] }} (ID: {{ $selectedLogDetails['subject_id'] }}) &mdash; {{ $selectedLogDetails['subject_label'] }}</p>
                        </div>
                    @endif
                    @if(!empty($selectedLogDetails['user_agent']))
                        <div class="sm:col-span-2 md:col-span-3">
                            <span class="text-zinc-400 uppercase font-bold text-[10px]">{{ __('Full User Agent') }}:</span>
                            <p class="font-mono text-[11px] text-zinc-600 dark:text-zinc-400 mt-0.5 break-all bg-zinc-100 dark:bg-zinc-800/60 p-2 rounded-lg border border-zinc-200 dark:border-zinc-700/50">{{ $selectedLogDetails['user_agent'] }}</p>
                        </div>
                    @endif
                </div>

                @if(!empty($selectedLogDetails['properties']))
                    <div class="space-y-2">
                        <flux:label class="font-bold text-xs uppercase text-zinc-500">{{ __('Logged Properties / Attributes Diff') }}</flux:label>
                        <div class="bg-zinc-950 text-zinc-200 p-4 rounded-xl text-xs font-mono max-h-72 overflow-x-auto overflow-y-auto">
                            <pre class="whitespace-pre-wrap break-all">{{ json_encode($selectedLogDetails['properties'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button wire:click="$set('showDetailsModal', false)">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Purge Activity Logs Modal --}}
    <flux:modal wire:model="showClearModal" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Purge Activity Audit Logs') }}</flux:heading>
                <flux:subheading>{{ __('Select the age threshold of activity logs to permanently delete.') }}</flux:subheading>
            </div>

            <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-900/50 flex gap-3">
                <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <div class="text-xs text-amber-800 dark:text-amber-300 space-y-1">
                    <p class="font-bold">{{ __('Caution: Permanent Action') }}</p>
                    <p>{{ __('Purged logs will be permanently deleted from the database and cannot be recovered.') }}</p>
                </div>
            </div>

            <flux:field>
                <flux:label>{{ __('Retention Threshold') }}</flux:label>
                <flux:select wire:model="clearPeriod">
                    <option value="30_days">{{ __('Logs older than 30 days (Recommended)') }}</option>
                    <option value="90_days">{{ __('Logs older than 90 days') }}</option>
                    <option value="180_days">{{ __('Logs older than 180 days') }}</option>
                    <option value="all">{{ __('ALL historical activity logs') }}</option>
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showClearModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="purgeLogs">{{ __('Confirm Purge') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
