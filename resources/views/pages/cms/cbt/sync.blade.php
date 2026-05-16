<?php

use App\Models\CbtSyncLog;
use App\Models\CbtResultStaging;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('CBT Connectivity & Sync')] class extends Component {
    use WithPagination;

    public string $tokenName = '';
    public string $generatedToken = '';
    public bool $showTokenModal = false;

    public function mount(): void
    {
        Gate::authorize('cbt_sync.view');
    }

    public function refresh(): void
    {
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Status synchronized with database.',
        ]);
    }

    public function generateToken(): void
    {
        Gate::authorize('cbt_sync.manage_tokens');
        $this->validate([
            'tokenName' => 'required|string|min:3|max:50',
        ]);

        $token = auth()->user()->createToken('cbt_lab_' . $this->tokenName, ['cbt:sync']);
        $this->generatedToken = $token->plainTextToken;
        $this->showTokenModal = true;
        $this->tokenName = '';
    }

    public function revokeToken($id): void
    {
        Gate::authorize('cbt_sync.manage_tokens');
        auth()->user()->tokens()->where('id', $id)->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Access token revoked.',
        ]);
    }

    public function with(): array
    {
        $instId = auth()->user()->institution_id;

        return [
            'stats' => [
                'last_sync' => CbtSyncLog::where('institution_id', $instId)->latest()->first(),
                'pending_results' => CbtResultStaging::whereHas('exam', function ($q) use ($instId) {
                    $q->where('institution_id', $instId);
                })->where('status', 'pending')->count(),
                'sync_count' => CbtSyncLog::where('institution_id', $instId)->where('status', 'success')->count(),
                'failed_count' => CbtSyncLog::where('institution_id', $instId)->where('status', '!=', 'success')->count(),
            ],
            'logs' => CbtSyncLog::where('institution_id', $instId)
                ->latest()
                ->paginate(20),
            'tokens' => auth()->user()->tokens()
                ->where('name', 'like', 'cbt_lab_%')
                ->get(),
        ];
    }
}; ?>

<div class="p-6 lg:p-10 space-y-12 max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Connectivity Dashboard') }}</flux:heading>
            <flux:subheading>{{ __('Monitor real-time data exchange and audit logs for Lab Servers.') }}
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @can('cbt_sync.manage_tokens')
                <flux:button variant="primary" icon="plus" wire:click="$set('showTokenModal', true)">{{ __('New Token') }}
                </flux:button>
            @endcan
            <flux:button variant="subtle" icon="arrow-path" wire:click="refresh">{{ __('Refresh Status') }}
            </flux:button>
        </div>
    </div>

    {{-- System Status & Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <flux:card
            class="p-6 flex items-center justify-between bg-zinc-900 text-white dark:bg-black border-none relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 translate-x-1/4 -translate-y-1/4">
                <flux:icon icon="server-stack" class="size-48" />
            </div>

            <div class="relative z-10">
                <flux:text size="sm" class="uppercase tracking-widest font-bold text-zinc-400 mb-2">
                    {{ __('System Status') }}
                </flux:text>
                @if($stats['last_sync'] && $stats['last_sync']->created_at->diffInMinutes(now()) < 60)
                    <div class="flex items-center gap-3">
                        <div class="relative flex size-4">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-4 bg-green-500"></span>
                        </div>
                        <span class="text-2xl font-black text-white">{{ __('Online & Active') }}</span>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <div class="relative flex size-4">
                            <span class="relative inline-flex rounded-full size-4 bg-zinc-500"></span>
                        </div>
                        <span class="text-2xl font-black text-white">{{ __('Standby') }}</span>
                    </div>
                @endif

                <div class="mt-4 text-sm text-zinc-400">
                    {{ __('Last seen:') }}
                    <span class="font-bold text-white">
                        @if($stats['last_sync'])
                            {{ $stats['last_sync']->created_at->diffForHumans() }}
                        @else
                            {{ __('Never') }}
                        @endif
                    </span>
                </div>
            </div>
        </flux:card>

        <flux:card
            class="p-6 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 flex flex-col justify-between">
            <div class="flex justify-between items-start">
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-lg">
                    <flux:icon icon="arrows-right-left" class="size-5" />
                </div>
                <flux:badge size="sm" color="zinc" variant="outline">{{ __('All Time') }}</flux:badge>
            </div>
            <div class="mt-4">
                <flux:text size="xs" class="uppercase font-bold text-zinc-500 mb-1">{{ __('Successful Syncs') }}
                </flux:text>
                <div class="text-3xl font-black">{{ $stats['sync_count'] }}</div>
            </div>
        </flux:card>

        <flux:card
            class="p-6 bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 flex flex-col justify-between">
            <div class="flex justify-between items-start">
                <div class="p-2 bg-orange-50 dark:bg-orange-900/30 text-orange-600 rounded-lg">
                    <flux:icon icon="document-text" class="size-5" />
                </div>
                <flux:button size="xs" variant="ghost" :href="route('cms.cbt.results')" wire:navigate>{{ __('Review') }}
                    &rarr;</flux:button>
            </div>
            <div class="mt-4">
                <flux:text size="xs" class="uppercase font-bold text-zinc-500 mb-1">{{ __('Pending Audits') }}
                </flux:text>
                <div class="text-3xl font-black text-orange-600">{{ $stats['pending_results'] }}</div>
            </div>
        </flux:card>

        <flux:card
            class="p-6 bg-zinc-50 dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 flex flex-col justify-between">
            <div class="flex justify-between items-start">
                <div class="p-2 bg-purple-50 dark:bg-purple-900/30 text-purple-600 rounded-lg">
                    <flux:icon icon="key" class="size-5" />
                </div>
                <flux:badge size="sm" color="purple" variant="outline">{{ __('Active') }}</flux:badge>
            </div>
            <div class="mt-4">
                <flux:text size="xs" class="uppercase font-bold text-zinc-500 mb-1">{{ __('Lab Access Tokens') }}
                </flux:text>
                <div class="text-3xl font-black text-purple-600">{{ count($tokens) }}</div>
            </div>
        </flux:card>
    </div>


    {{-- Token Management --}}

    <div
        class="p-6 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between bg-zinc-50 dark:bg-zinc-900/50">
        <div>
            <flux:heading size="lg">{{ __('Lab Server Access Tokens') }}</flux:heading>
            <flux:text size="sm" class="text-zinc-500">{{ __('Secure tokens for CBT Lab Server authentication.') }}
            </flux:text>
        </div>
    </div>
    <div class="p-0">
        @if(count($tokens) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="px-8">{{ __('Server / Label') }}</flux:table.column>
                    <flux:table.column class="px-8">{{ __('Last Used') }}</flux:table.column>
                    <flux:table.column class="px-8"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($tokens as $token)
                        <flux:table.row>
                            <flux:table.cell class="px-8 py-4">
                                <div class="font-bold text-zinc-900 dark:text-white">
                                    {{ str_replace('cbt_lab_', '', $token->name) }}
                                </div>
                                <div class="text-[10px] font-mono text-zinc-400 uppercase tracking-tighter">{{ __('ID:') }}
                                    {{ $token->id }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="px-8">
                                <span class="text-xs text-zinc-500">
                                    {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : __('Never used') }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell class="px-8 text-right">
                                @can('cbt_sync.manage_tokens')
                                    <flux:button variant="ghost" icon="trash" size="xs" color="red"
                                        wire:click="revokeToken({{ $token->id }})"
                                        wire:confirm="{{ __('Revoke this access token?') }}" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <div class="p-12 text-center text-zinc-500 italic text-sm">
                {{ __('No active lab server tokens found.') }}
            </div>
        @endif
    </div>

    {{-- Token Generation Modal --}}
    <flux:modal wire:model="showTokenModal" class="w-full max-w-lg">
        <div class="space-y-6">
            @if($generatedToken)
                <div class="text-center">
                    <div
                        class="size-16 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 flex items-center justify-center mx-auto mb-4">
                        <flux:icon icon="key" class="size-8" />
                    </div>
                    <flux:heading size="lg">{{ __('Token Generated Successfully') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Copy this token now. It will not be shown again for security reasons.') }}
                    </flux:text>
                </div>

                <div
                    class="p-4 bg-zinc-100 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 break-all font-mono text-sm text-center">
                    {{ $generatedToken }}
                </div>

                <div class="flex justify-center">
                    <flux:button variant="primary" wire:click="$set('generatedToken', ''); $set('showTokenModal', false)">
                        {{ __('I have copied the token') }}
                    </flux:button>
                </div>
            @else
                <div>
                    <flux:heading size="lg">{{ __('Generate Lab Access Token') }}</flux:heading>
                    <flux:subheading>{{ __('Identify this server with a unique label (e.g., Lab 1, Annex Server).') }}
                    </flux:subheading>
                </div>

                <flux:input label="{{ __('Server Label') }}" wire:model="tokenName"
                    placeholder="{{ __('e.g. Main Lab Server') }}" />

                <div class="flex gap-2 justify-end pt-4">
                    <flux:button wire:click="showTokenModal = false">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" wire:click="generateToken">{{ __('Generate Token') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>