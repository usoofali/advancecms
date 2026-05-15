@use('App\Models\Institution')
<div>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-zinc-100 dark:bg-zinc-900 pb-12">
        <div class="w-full sm:max-w-4xl mt-6 px-6 py-8 bg-white dark:bg-zinc-800 shadow-md overflow-hidden sm:rounded-lg">

            @if($showConfirmation)
                {{-- ── Step 2: Email Sent Confirmation ── --}}
                <div class="text-center py-4 space-y-5">
                    {{-- Success Icon --}}
                    <div class="flex items-center justify-center">
                        <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
                            <flux:icon.envelope-open class="w-8 h-8 text-green-600" />
                        </div>
                    </div>

                    <div>
                        <flux:heading size="xl">{{ __('Check Your Email') }}</flux:heading>
                        <flux:subheading class="mt-1">
                            {{ __('An email has been sent to') }}
                            <strong class="text-zinc-900 dark:text-white">{{ $applicant?->email }}</strong>
                        </flux:subheading>
                    </div>

                    <div class="text-left bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 space-y-2 text-sm text-blue-800 dark:text-blue-300">
                        <p class="font-semibold">{{ __('What is in the email?') }}</p>
                        <ul class="space-y-1 list-disc list-inside">
                            <li>{{ __('Your Application Number:') }} <strong>{{ $applicant?->application_number }}</strong></li>
                            <li>{{ __('A secure link to your Applicant Portal') }}</li>
                            <li>{{ __('Instructions on how to track your application status') }}</li>
                        </ul>
                    </div>

                    <div class="text-left bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-300">
                        <p class="font-semibold">{{ __('Why is this important?') }}</p>
                        <p class="mt-1">
                            {{ __('After payment, use the link in your email to access your Applicant Portal where you will submit your academic credentials, track your admission review, and receive your admission letter.') }}
                        </p>
                    </div>

                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __("Didn't receive an email? Check your spam folder or contact the admissions office.") }}
                    </p>

                    <flux:button wire:click="proceedToPayment" variant="primary" class="w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="proceedToPayment">
                            {{ __('Proceed to Payment') }}
                        </span>
                        <span wire:loading wire:target="proceedToPayment">
                            {{ __('Redirecting to payment...') }}
                        </span>
                    </flux:button>
                </div>

            @else
                {{-- ── Step 1: Application Form ── --}}
                <div class="mb-6 text-center">
                    <flux:heading size="xl">{{ $mode === 'apply' ? __('Purchase Application Form') : __('Resume Application') }}</flux:heading>
                    <flux:subheading>{{ $mode === 'apply' ? __('Start your admission journey today.') : __('Continue where you left off.') }}</flux:subheading>
                </div>

                <div class="flex items-center justify-center gap-2 mb-6 p-1 bg-zinc-200 dark:bg-zinc-700/50 rounded-lg">
                    <button wire:click="setMode('apply')" type="button" class="flex-1 py-1.5 px-3 text-sm font-medium rounded-md transition-all {{ $mode === 'apply' ? 'bg-white dark:bg-zinc-600 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">New Application</button>
                    <button wire:click="setMode('resume')" type="button" class="flex-1 py-1.5 px-3 text-sm font-medium rounded-md transition-all {{ $mode === 'resume' ? 'bg-white dark:bg-zinc-600 shadow-sm text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white' }}">Resume</button>
                </div>

                @if($mode === 'apply')
                <div class="space-y-6">
                    {{-- Cards Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse($forms as $form)
                            <flux:card class="flex flex-col h-full border border-zinc-200 dark:border-zinc-700 hover:border-blue-500 dark:hover:border-blue-500 transition-colors shadow-sm group">
                                <div class="flex-1 space-y-4">
                                    @if($form->institution?->logo_url)
                                        <div class="w-12 h-12 bg-white dark:bg-zinc-800 rounded-lg flex items-center justify-center p-1 border border-zinc-100 dark:border-zinc-700">
                                            <img src="{{ $form->institution->logo_url }}" alt="{{ $form->institution->name }}" class="max-w-full max-h-full object-contain rounded-md" />
                                        </div>
                                    @else
                                        <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex items-center justify-center text-blue-600 dark:text-blue-400">
                                            <flux:icon.document-text class="size-6" />
                                        </div>
                                    @endif
                                    <div>
                                        <flux:heading size="lg" class="group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $form->name }}</flux:heading>
                                        <flux:text size="sm" class="text-zinc-500 mt-1">{{ $form->institution?->name }}</flux:text>
                                        <flux:text size="sm" class="text-zinc-400 mt-0.5">{{ $form->academicSession?->name ?? 'Current Session' }}</flux:text>
                                    </div>
                                    <div class="pt-2">
                                        <flux:text weight="bold" class="text-2xl text-zinc-900 dark:text-white">₦{{ number_format($form->amount, 2) }}</flux:text>
                                    </div>
                                </div>
                                <div class="pt-6 mt-auto">
                                    <flux:button variant="primary" class="w-full" wire:click="selectForm({{ $form->id }})">
                                        {{ __('Purchase Form') }}
                                    </flux:button>
                                </div>
                            </flux:card>
                            @empty
                            <div class="col-span-full py-8 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
                                <flux:icon.inbox class="size-8 mx-auto mb-3 text-zinc-400" />
                                <p>{{ __('No application forms available at the moment.') }}</p>
                            </div>
                            @endforelse
                    </div>
                </div>

                {{-- Purchase Form Modal --}}
                <flux:modal name="purchase-form" class="min-w-[400px]">
                    <form wire:submit="submit" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Complete Purchase') }}</flux:heading>
                            <flux:subheading>{{ __('Provide your details to continue.') }}</flux:subheading>
                        </div>

                        <div class="space-y-4">
                            <flux:select wire:model.live="program_id" :label="__('Select Program')" required>
                                <flux:select.option value="">{{ __('Choose a program...') }}</flux:select.option>
                                @foreach($programs as $prog)
                                    <flux:select.option :value="$prog->id">{{ $prog->name }} ({{ $prog->acronym }})</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="full_name" :label="__('Full Name')" placeholder="John Doe" required />

                            <flux:input wire:model="email" type="email" :label="__('Email Address')" placeholder="john@example.com" required />
                            
                            <flux:input wire:model="phone" :label="__('Phone Number')" placeholder="08012345678" required />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <flux:modal.close>
                                <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">{{ __('Proceed to Payment') }}</span>
                                <span wire:loading wire:target="submit">{{ __('Processing...') }}</span>
                            </flux:button>
                        </div>
                    </form>
                </flux:modal>
                @else
                <form wire:submit="resumeApplication" class="space-y-6">
                    <div class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <flux:heading size="lg">{{ __('Applicant Details') }}</flux:heading>
                        <p class="text-sm text-zinc-500 mb-4">{{ __('Enter the email address and phone number you used during your initial application to resume your session.') }}</p>
                        
                        <flux:input wire:model="resumeEmail" type="email" :label="__('Email Address')" placeholder="john@example.com" required />
                        <flux:input wire:model="resumePhone" :label="__('Phone Number')" placeholder="08012345678" required />
                    </div>
                    
                    <div class="flex items-center justify-end mt-4">
                        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="resumeApplication">{{ __('Resume Application') }}</span>
                            <span wire:loading wire:target="resumeApplication">{{ __('Searching...') }}</span>
                        </flux:button>
                    </div>
                </form>
                @endif
            @endif

        </div>
    </div>
</div>
