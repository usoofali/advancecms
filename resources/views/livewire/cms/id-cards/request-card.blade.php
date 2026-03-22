<div class="mx-auto max-w-2xl">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('ID Card Request') }}</flux:heading>
        <flux:subheading>{{ __('Request a new or replacement identification card.') }}</flux:subheading>
    </div>

    @if($this->profile && !$this->profile->photo_path)
        <flux:card class="mb-6 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700/50">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-amber-100 dark:bg-amber-800 rounded-lg">
                    <flux:icon.camera class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1">
                    <flux:heading size="sm" class="text-amber-900 dark:text-amber-100">{{ __('Photo Required') }}</flux:heading>
                    <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                        {{ __('You must upload a professional profile photo before you can request an ID card.') }}
                    </flux:text>
                    <div class="mt-2">
                        <flux:button variant="ghost" size="sm" icon="pencil-square" href="{{ route('profile.edit') }}">
                            {{ __('Go to Profile Settings') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <form wire:submit="submit" class="space-y-6">
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Request Details') }}</flux:heading>
                
                <flux:select wire:model.live="reason" :label="__('Reason for Request')" icon="question-mark-circle">
                    <flux:select.option value="first_issue">{{ __('First-Time Issuance') }}</flux:select.option>
                    <flux:select.option value="loss">{{ __('Lost ID Card') }}</flux:select.option>
                    <flux:select.option value="replacement">{{ __('Damaged / Defaced Replacement') }}</flux:select.option>
                </flux:select>

                @if($reason !== 'first_issue' && auth()->user()->hasRole('Student'))
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-4">
                        <flux:heading size="xs" weight="bold" class="text-zinc-600 dark:text-zinc-400 uppercase tracking-widest">{{ __('Payment Verification') }}</flux:heading>
                        
                        @if($this->paidInvoices->isEmpty())
                            <div class="flex items-center gap-3 text-red-600 dark:text-red-400">
                                <flux:icon.exclamation-triangle class="size-5 shrink-0" />
                                <span class="text-sm font-medium">{{ __('No paid ID card replacement invoices found.') }}</span>
                            </div>
                            <flux:text size="xs">{{ __('Please contact the Bursary/College Accountant to generate a replacement invoice first.') }}</flux:text>
                        @else
                            <flux:select wire:model="student_invoice_id" :label="__('Select Paid Invoice Reference')" icon="credit-card">
                                <flux:select.option value="">{{ __('Select a paid invoice...') }}</flux:select.option>
                                @foreach($this->paidInvoices as $invoice)
                                    <flux:select.option :value="$invoice->id">
                                        {{ $invoice->invoice->title }} (Paid: ₦{{ number_format($invoice->amount_paid, 2) }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </div>
                @endif
            </div>

            <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                @if($this->currentSession)
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <flux:icon.calendar class="size-4" />
                        <span>Session: <strong>{{ $this->currentSession->name }}</strong></span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-xs text-red-500">
                        <flux:icon.exclamation-circle class="size-4" />
                        <span>{{ __('No active session found.') }}</span>
                    </div>
                @endif

                <flux:button variant="primary" type="submit" class="w-full md:w-auto" :disabled="!$this->profile?->photo_path || ($reason !== 'first_issue' && auth()->user()->hasRole('Student') && $this->paidInvoices->isEmpty())">
                    {{ __('Submit Request') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
