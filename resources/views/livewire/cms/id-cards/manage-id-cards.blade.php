<div class="mx-auto max-w-7xl">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('ID Card Management') }}</flux:heading>
            <flux:subheading>{{ __('Manage student and staff identification card requests and generation.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:radio.group wire:model.live="view_mode" variant="segmented" size="sm">
                <flux:radio value="requests" :label="__('Requests')" />
                <flux:radio value="direct" :label="__('Bulk Generation')" />
            </flux:radio.group>
        </div>
    </div>

    <flux:card class="mb-8 p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" :label="__('Institution')" icon="building-library">
                    <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                    @foreach($this->institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="type" :label="__('Role Type')" icon="users">
                <flux:select.option value="student">{{ __('Students') }}</flux:select.option>
                <flux:select.option value="staff">{{ __('Staff') }}</flux:select.option>
            </flux:select>

            @if($view_mode === 'requests')
                <flux:select wire:model.live="status" :label="__('Request Status')" icon="check-circle">
                    <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="issued">{{ __('Issued') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                </flux:select>
            @else
                @if($type === 'student')
                    <flux:select wire:model.live="program_id" :label="__('Program')" icon="academic-cap">
                        <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
                        @foreach($this->programs as $prog)
                            <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            @endif

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Name, ID, or Email...') }}" icon="magnifying-glass" />
        </div>
    </flux:card>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <flux:heading size="md">{{ $view_mode === 'requests' ? __('ID Card Requests') : __('Staff/Student Registry') }}</flux:heading>
                <flux:badge size="sm" color="zinc" variant="neutral" inset="top bottom">{{ $results->total() }} {{ __('Total') }}</flux:badge>
            </div>

            @if(count($selected_ids) > 0)
                <div class="flex items-center gap-2 animate-in fade-in slide-in-from-right-4">
                    <flux:text size="sm" class="font-bold text-blue-600">{{ count($selected_ids) }} {{ __('selected') }}</flux:text>
                    <flux:button variant="primary" size="sm" icon="printer" wire:click="bulkGenerate">
                        {{ __('Generate Selected Cards') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700/50 rounded-2xl bg-white dark:bg-zinc-800/30">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            {{-- We handle select all manually for now as flux checkbox inside row might conflict --}}
                        </th>
                        <th class="px-4 py-3 font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ __('ID / Name') }}</th>
                        @if($view_mode === 'requests')
                            <th class="px-4 py-3 font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ __('Reason') }}</th>
                            <th class="px-4 py-3 font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ __('Invoice Status') }}</th>
                        @else
                            <th class="px-4 py-3 font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ $type === 'student' ? __('Program') : __('Designation') }}</th>
                        @endif
                        <th class="px-4 py-3 font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ __('Photo Status') }}</th>
                        <th class="px-4 py-3 text-right font-bold text-zinc-900 dark:text-white uppercase tracking-wider text-[10px]">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($results as $item)
                        @php
                            // Determine user and profile based on view mode
                            if ($view_mode === 'requests') {
                                $user = $item->user;
                                $profile = $type === 'student' ? $user->student : $user->staff;
                            } else {
                                $profile = $item;
                                $user = $item->user;
                            }
                            
                            $displayName = $user?->name ?? 'Unknown';
                            $displayId = $type === 'student' ? ($profile->matric_number ?? 'N/A') : ($profile->staff_number ?? 'N/A');
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3">
                                <flux:checkbox wire:model="selected_ids" value="{{ $item->id }}" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-bold text-zinc-900 dark:text-white">{{ $displayName }}</span>
                                    <span class="text-xs text-zinc-500 font-mono">{{ $displayId }}</span>
                                </div>
                            </td>
                            @if($view_mode === 'requests')
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" color="zinc" variant="neutral">{{ ucfirst(str_replace('_', ' ', $item->reason)) }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    @if($item->studentInvoice)
                                        <div class="flex items-center gap-1 text-green-600 font-bold text-xs">
                                            <flux:icon.check-circle class="size-3" />
                                            <span>Paid</span>
                                        </div>
                                    @else
                                        @if($item->reason !== 'first_issue' && $type === 'student')
                                            <span class="text-[10px] text-red-500 font-bold uppercase tracking-widest">No Invoice</span>
                                        @else
                                            <span class="text-[10px] text-zinc-400 uppercase tracking-widest italic">Free</span>
                                        @endif
                                    @endif
                                </td>
                            @else
                                <td class="px-4 py-3">
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $type === 'student' ? ($profile->program?->name ?? 'N/A') : ($profile->designation ?? 'N/A') }}
                                    </span>
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-lg overflow-hidden border-2 border-zinc-100 dark:border-zinc-800 shadow-sm bg-zinc-200 dark:bg-zinc-900">
                                        @if($profile->photo_path)
                                            <img src="{{ asset('storage/'.$profile->photo_path) }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.camera class="size-4" />
                                            </div>
                                        @endif
                                    </div>
                                    @if(!$profile->photo_path)
                                        <flux:badge size="sm" color="red" variant="neutral">{{ __('Missing') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green" variant="neutral">{{ __('Ready') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($view_mode === 'requests' && $item->status === 'pending')
                                        <flux:button variant="ghost" size="xs" color="green" icon="check" wire:click="approveRequest({{ $item->id }})" title="{{ __('Approve Request') }}" />
                                        <flux:button variant="ghost" size="xs" color="red" icon="x-mark" wire:click="rejectRequest({{ $item->id }})" title="{{ __('Reject Request') }}" />
                                    @endif
                                    
                                    <flux:button :disabled="!$profile->photo_path" icon="printer" variant="ghost" size="xs" wire:click="$set('selected_ids', [{{ $item->id }}]); bulkGenerate()" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-zinc-500">
                                <div class="flex flex-col items-center">
                                    <div class="p-4 bg-zinc-100 dark:bg-zinc-800/50 rounded-full mb-4">
                                        <flux:icon.identification class="size-12 opacity-20" />
                                    </div>
                                    <p class="font-medium">{{ __('No ID card records found.') }}</p>
                                    <p class="text-xs text-zinc-400 mt-1">{{ __('Try adjusting your filters or search terms.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $results->links() }}
        </div>
    </div>
</div>
