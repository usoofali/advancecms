@use('App\Models\Institution')
<div>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-zinc-100 dark:bg-zinc-900">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-zinc-800 shadow-md overflow-hidden sm:rounded-lg">

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
                    <flux:heading size="xl">{{ __('Purchase Application Form') }}</flux:heading>
                    <flux:subheading>{{ __('Start your admission journey today.') }}</flux:subheading>
                </div>

                <form wire:submit="submit" class="space-y-6">
                    {{-- Institution & Program Selection --}}
                    <div class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <flux:heading size="lg">{{ __('Academic Details') }}</flux:heading>
                        <flux:select wire:model.live="institution_id" :label="__('Select Institution')">
                            <flux:select.option value="">{{ __('Choose an institution...') }}</flux:select.option>
                            @foreach($institutions as $inst)
                                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if($institution_id)
                            <flux:select wire:model.live="program_id" :label="__('Select Program')">
                                <flux:select.option value="">{{ __('Choose a program...') }}</flux:select.option>
                                @foreach($programs as $prog)
                                    <flux:select.option :value="$prog->id">{{ $prog->name }} ({{ $prog->acronym }})</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="application_form_id" :label="__('Select Application Form')">
                                <flux:select.option value="">{{ __('Choose a form...') }}</flux:select.option>
                                @foreach($forms as $form)
                                    <flux:select.option :value="$form->id">{{ $form->name }} (₦{{ number_format($form->amount, 2) }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if($institution_id && !Institution::find($institution_id)?->isAdmissionActive())
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400 text-sm flex items-start gap-2">
                                <flux:icon.exclamation-circle class="size-5 shrink-0" />
                                <div>
                                    <p class="font-bold">{{ __('Admissions Closed') }}</p>
                                    <p>{{ __('New applications are not being accepted for this institution at this time.') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Personal Information --}}
                    <div class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <flux:heading size="lg">{{ __('Personal Details') }}</flux:heading>
                        <flux:input wire:model="full_name" :label="__('Full Name')" placeholder="John Doe" required />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:input wire:model="email" type="email" :label="__('Email Address')" placeholder="john@example.com" required />
                            <flux:input wire:model="phone" :label="__('Phone Number')" placeholder="08012345678" required />
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled"
                            :disabled="$institution_id && !Institution::find($institution_id)?->isAdmissionActive()">
                            <span wire:loading.remove wire:target="submit">
                                {{ __('Proceed to Payment') }}
                            </span>
                            <span wire:loading wire:target="submit">
                                {{ __('Processing...') }}
                            </span>
                        </flux:button>
                    </div>
                </form>
            @endif

        </div>
    </div>
</div>
