<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        @if (auth()->user()->hasAnyRole(['Super Admin', 'Institutional Admin']))
            <livewire:cms.dashboards.admin-dashboard />
        @elseif (auth()->user()->hasRole('Student'))
            <livewire:cms.dashboards.student-dashboard />
        @else
            <livewire:cms.dashboards.staff-dashboard />
        @endif
    </div>
</x-layouts::app>
