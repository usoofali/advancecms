<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\StudentPlacement;
use App\Models\Organization;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        $stats = [
            'total' => StudentPlacement::count(),
            'pending' => StudentPlacement::where('status', 'Pending')->count(),
            'assigned' => StudentPlacement::where('status', 'Assigned')->count(),
            'accepted' => StudentPlacement::where('status', 'Accepted')->count(),
            'rejected' => StudentPlacement::where('status', 'Rejected')->count(),
            'completed' => StudentPlacement::where('status', 'Completed')->count(),
        ];
        
        $topOrgs = Organization::withCount('placements')
            ->orderBy('placements_count', 'desc')
            ->take(5)
            ->get();
            
        $recentPlacements = StudentPlacement::with(['student', 'organization'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return compact('stats', 'topOrgs', 'recentPlacements');
    }
};
?>

<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Placements Dashboard</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview of student placements and organization utilization.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Posted</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
                </div>
                <div class="p-3 bg-blue-100 text-blue-600 rounded-full dark:bg-blue-900 dark:text-blue-300">
                    <flux:icon.users class="w-6 h-6" />
                </div>
            </div>
        </flux:card>
        
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Accepted</p>
                    <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $stats['accepted'] }}</p>
                </div>
                <div class="p-3 bg-green-100 text-green-600 rounded-full dark:bg-green-900 dark:text-green-300">
                    <flux:icon.check-circle class="w-6 h-6" />
                </div>
            </div>
        </flux:card>
        
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending</p>
                    <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ $stats['pending'] + $stats['assigned'] }}</p>
                </div>
                <div class="p-3 bg-yellow-100 text-yellow-600 rounded-full dark:bg-yellow-900 dark:text-yellow-300">
                    <flux:icon.clock class="w-6 h-6" />
                </div>
            </div>
        </flux:card>
        
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Rejected</p>
                    <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $stats['rejected'] }}</p>
                </div>
                <div class="p-3 bg-red-100 text-red-600 rounded-full dark:bg-red-900 dark:text-red-300">
                    <flux:icon.x-circle class="w-6 h-6" />
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Assignments -->
        <div class="lg:col-span-2">
            <flux:card>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Recent Placements</h3>
                
                <div class="relative overflow-x-auto">
                    <flux:table :paginate="$recentPlacements">
                        <flux:table.columns>
                            <flux:table.column>Student</flux:table.column>
                            <flux:table.column>Organization</flux:table.column>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse($recentPlacements as $placement)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $placement->student->full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $placement->student->matric_number }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ Str::limit($placement->organization_display_name, 30) }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $placement->created_at->diffForHumans() }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge color="gray" size="sm">{{ $placement->status }}</flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-4 text-gray-500">
                                        No recent placements found.
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>
        
        <!-- Organization Utilization -->
        <div>
            <flux:card>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Top Organizations</h3>
                
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($topOrgs as $org)
                        <li class="py-3 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $org->name }}</p>
                                <p class="text-xs text-gray-500">{{ $org->city }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $org->placements_count }} Students
                                </span>
                                <p class="text-xs text-gray-500 mt-1">Cap: {{ $org->capacity }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-center text-sm text-gray-500">No data available.</li>
                    @endforelse
                </ul>
            </flux:card>
        </div>
    </div>
</div>
