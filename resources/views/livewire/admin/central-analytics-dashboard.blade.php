<div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Top Header Card -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5 bg-gradient-to-r from-emerald-800 via-teal-700 to-cyan-800 text-white p-6 rounded-2xl shadow-xl shadow-emerald-900/10 border border-emerald-600/30 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex items-center gap-4">
            <div class="p-3 bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 shadow-inner">
                <svg class="w-7 h-7 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V9a2 2 0 012-2h2a2 2 0 012 2v12m-6 0a2 2 0 002-2v-4a2 2 0 00-2-2h-2a2 2 0 00-2 2v4a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-white">Central Gateway & Multi-Tenant Analytics</h1>
                <p class="text-xs text-emerald-100/90 mt-0.5 font-medium">Direct MySQL Data Aggregation & WhatsApp Business API Gateway</p>
            </div>
        </div>

        <div class="relative z-10 flex items-center gap-3">
            <button wire:click="triggerHarvest" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white font-semibold text-sm rounded-xl backdrop-blur-md border border-white/20 transition shadow-sm disabled:opacity-50 cursor-pointer">
                <svg class="w-4 h-4 text-emerald-200" wire:loading.class="animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Trigger Metrics Harvest</span>
            </button>

            <button wire:click="openNewTenantModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-400 hover:bg-emerald-300 text-emerald-950 font-bold text-sm rounded-xl transition shadow-lg shadow-emerald-950/20 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Spoke Campus</span>
            </button>
        </div>
    </div>

    @if ($feedbackMessage)
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-xl flex items-center justify-between text-emerald-900 text-sm shadow-xs font-medium">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
        </div>
    @endif

    <!-- Aggregate Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200/90 hover:border-emerald-300 hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Enrollment</span>
                <span class="p-2.5 bg-sky-50 text-sky-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3">{{ number_format($totals['total_students']) }}</p>
            <p class="text-xs text-slate-500 mt-1 font-medium">Across all registered Spoke campuses</p>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200/90 hover:border-emerald-300 hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Students</span>
                <span class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3">{{ number_format($totals['active_students']) }}</p>
            <p class="text-xs text-emerald-700 mt-1 font-semibold">Currently active in academic session</p>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200/90 hover:border-emerald-300 hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Staff</span>
                <span class="p-2.5 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3">{{ number_format($totals['total_staff']) }}</p>
            <p class="text-xs text-slate-500 mt-1 font-medium">Academic & Administrative personnel</p>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-xs border border-slate-200/90 hover:border-emerald-300 hover:shadow-md transition duration-200">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Fees Harvested</span>
                <span class="p-2.5 bg-teal-50 text-teal-600 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 mt-3">₦{{ number_format($totals['fees_collected'], 2) }}</p>
            <p class="text-xs text-teal-700 mt-1 font-semibold">Aggregated revenue collected</p>
        </div>
    </div>

    <!-- Tenant Spoke Campuses List -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/90 overflow-hidden">
        <div class="p-5 border-b border-slate-200/80 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Registered Spoke Campuses</h3>
                <p class="text-xs text-slate-500 font-medium">Active tenant nodes configured for WhatsApp routing and metrics harvesting</p>
            </div>
            <div class="w-full sm:w-72">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search campus code or name..." class="w-full px-4 py-2 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-semibold text-slate-900 placeholder-slate-400 shadow-xs transition">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead class="bg-slate-50/90 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Campus Name</th>
                        <th class="px-6 py-4">Campus Code</th>
                        <th class="px-6 py-4">Direct DB Connection</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tenants as $tenant)
                        <tr class="hover:bg-slate-50/80 transition duration-150">
                            <td class="px-6 py-4 font-bold text-slate-900">{{ $tenant->name }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-mono font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg inline-block">{{ $tenant->code }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs font-mono text-slate-600 truncate max-w-xs">
                                <span class="font-semibold text-slate-900">{{ $tenant->db_username ?: 'root' }}</span>@<span class="text-slate-700">{{ $tenant->db_host }}:{{ $tenant->db_port }}</span>/<span class="font-semibold text-emerald-800">{{ $tenant->db_database }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($tenant->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300/60">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @elseif ($tenant->status === 'suspended')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full bg-rose-100 text-rose-800 border border-rose-300/60">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> Suspended
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-300/60">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Maintenance
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="editTenant({{ $tenant->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-emerald-50 text-emerald-700 hover:text-emerald-900 border border-slate-200 hover:border-emerald-300 text-xs font-bold rounded-xl transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    <span>Edit Config</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500 text-sm">
                                No registered campuses found. Click <strong class="text-emerald-700">"Add Spoke Campus"</strong> above to register your first tenant DB.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tenant Registration / Edit Modal -->
    @if ($showTenantModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-md">
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto border border-slate-200">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 bg-emerald-100 text-emerald-800 rounded-xl font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V9a2 2 0 012-2h2a2 2 0 012 2v12m-6 0a2 2 0 002-2v-4a2 2 0 00-2-2h-2a2 2 0 00-2 2v4a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-900">
                            {{ $editingTenantId ? 'Edit Spoke Campus Config' : 'Register New Spoke Campus' }}
                        </h3>
                    </div>
                    <button wire:click="$set('showTenantModal', false)" class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition text-lg font-bold">&times;</button>
                </div>

                <form wire:submit="saveTenant" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Campus Name</label>
                        <input type="text" wire:model="tenantName" placeholder="e.g. Main Campus / College of Tech" class="w-full px-4 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-sm font-semibold text-slate-900 placeholder-slate-400 transition duration-150">
                        @error('tenantName') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Campus Code</label>
                            <input type="text" wire:model="tenantCode" placeholder="e.g. MAIN" class="w-full px-4 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-sm font-mono font-bold text-slate-900 uppercase placeholder-slate-400 transition duration-150">
                            @error('tenantCode') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Status</label>
                            <select wire:model="tenantStatus" class="w-full px-4 py-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-sm font-semibold text-slate-900 transition duration-150">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <!-- Shared Hosting Database Credentials Section -->
                    <div class="p-4 bg-gradient-to-br from-emerald-50/60 to-teal-50/40 rounded-xl border border-emerald-200/80 space-y-3.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s-8-1.79-8-4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                            </svg>
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-950">Campus Direct MySQL Connection</span>
                        </div>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed">Configures direct database connectivity for WhatsApp user lookups and metrics harvesting.</p>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">DB Host</label>
                                <input type="text" wire:model="tenantDbHost" placeholder="127.0.0.1" class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg text-xs font-mono font-bold text-slate-900 placeholder-slate-400">
                                @error('tenantDbHost') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Port</label>
                                <input type="text" wire:model="tenantDbPort" placeholder="3306" class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg text-xs font-mono font-bold text-slate-900 placeholder-slate-400">
                                @error('tenantDbPort') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Database Name</label>
                            <input type="text" wire:model="tenantDbDatabase" placeholder="e.g. college_campus_db" class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg text-xs font-mono font-bold text-slate-900 placeholder-slate-400">
                            @error('tenantDbDatabase') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">DB Username</label>
                                <input type="text" wire:model="tenantDbUsername" placeholder="db_user" class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg text-xs font-mono font-bold text-slate-900 placeholder-slate-400">
                                @error('tenantDbUsername') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">DB Password</label>
                                <input type="password" wire:model="tenantDbPassword" placeholder="••••••••" class="w-full px-3 py-2 bg-white border border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg text-xs font-mono font-bold text-slate-900 placeholder-slate-400">
                                @error('tenantDbPassword') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" wire:click="$set('showTenantModal', false)" class="px-4 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl shadow-md cursor-pointer transition">Save Spoke Campus</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
