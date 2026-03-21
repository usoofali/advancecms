<div>
    <div class="min-h-screen pt-12 pb-24 bg-zinc-100 dark:bg-zinc-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8 pl-4">
                <flux:heading size="xl">{{ __('Applicant Portal') }}</flux:heading>
                <flux:subheading>{{ __('Application #') }}{{ $applicant->application_number }} - {{
                    $applicant->full_name }}</flux:subheading>
            </div>

            @php
                $institution = $applicant->institution;
                $isActive = $institution->isAdmissionActive();
                $endDate = $institution->admission_end_date;
                $isClosingSoon = $isActive && $endDate && $endDate->diffInHours(now()) < 48;
            @endphp

            @if(!$isActive)
                <div class="mb-8 p-4 bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl flex items-start gap-3">
                    <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400 shrink-0" />
                    <div>
                        <flux:heading size="lg" class="text-red-900 dark:text-red-100">{{ __('Admissions Closed') }}</flux:heading>
                        <flux:text class="text-red-800 dark:text-red-300">
                            {{ __('The admission window for this institution closed on ') }} <strong>{{ $endDate?->format('F j, Y, g:i a') ?? __('a specific date') }}</strong>. 
                            {{ __('New applications, credential submissions, and payments are strictly disabled.') }}
                        </flux:text>
                    </div>
                </div>
            @elseif($isClosingSoon)
                <div class="mb-8 p-4 bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-2xl flex items-start gap-3">
                    <flux:icon.clock class="size-6 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div>
                        <flux:heading size="lg" class="text-amber-900 dark:text-amber-100">{{ __('Closing Soon') }}</flux:heading>
                        <flux:text class="text-amber-800 dark:text-amber-300">
                            {{ __('Admissions for this institution will close in ') }} <strong>{{ $endDate->diffForHumans() }}</strong> ({{ $endDate->format('F j, Y, g:i a') }}). 
                            {{ __('Ensure all payments and credentials are submitted before the deadline.') }}
                        </flux:text>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Status Panel -->
                <div class="md:col-span-1 space-y-6">
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">{{ __('Application Status') }}</flux:heading>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ __('Payment') }}</span>
                                <flux:badge color="{{ $applicant->payment_status === 'paid' ? 'success' : 'warning' }}">
                                    {{ ucfirst($applicant->payment_status) }}
                                </flux:badge>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-zinc-600 dark:text-zinc-400">{{ __('Admission') }}</span>
                                <flux:badge color="{{ match($applicant->admission_status) {
                                    'admitted' => 'success',
                                    'rejected' => 'danger',
                                    default => 'zinc',
                                } }}">
                                    {{ ucfirst($applicant->admission_status) }}
                                </flux:badge>
                            </div>
                        </div>

                        @if($applicant->payment_status === 'pending')
                        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                                {{ __('Your application fee payment is still pending. You cannot complete your profile
                                until the fee is paid.') }}
                            </p>
                            <flux:button variant="primary" class="w-full" wire:click="retryPayment" :disabled="!$isActive">
                                {{ __('Retry Payment') }}
                            </flux:button>
                        </div>
                        @else
                        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                            <p class="text-sm text-green-600 dark:text-green-400 font-medium">
                                {{ __('Payment verified. Please complete your academic credentials on the right.') }}
                            </p>
                        </div>
                        @endif
                    </flux:card>

                    <flux:card>
                        <flux:heading size="lg" class="mb-4">{{ __('Program Details') }}</flux:heading>
                        <ul class="space-y-2 text-sm">
                            <li><span class="text-zinc-500">{{ __('Institution:') }}</span> <span
                                    class="font-medium dark:text-zinc-200">{{ $applicant->institution->name }}</span>
                            </li>
                            <li><span class="text-zinc-500">{{ __('Program:') }}</span> <span
                                    class="font-medium dark:text-zinc-200">{{ $applicant->program->name }}</span></li>
                            <li><span class="text-zinc-500">{{ __('Form Type:') }}</span> <span
                                    class="font-medium dark:text-zinc-200">{{ $applicant->applicationForm->name
                                    }}</span></li>
                        </ul>

                        @if($applicant->payment_status === 'paid' && ($receipt = $applicant->payments()->where('status',
                        'success')->latest()->first()?->receipt))
                        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button variant="ghost" class="w-full" icon="receipt-percent"
                                href="{{ route('applicant.receipt.print', ['receipt' => $receipt->receipt_number]) }}"
                                target="_blank">
                                {{ __('Download Receipt') }}
                            </flux:button>
                        </div>
                        @endif
                    </flux:card>

                    @if($applicant->admission_status === 'admitted')
                    @php
                    $admissionInvoice = $applicant->studentInvoices()->with('invoice')->latest()->first();
                    @endphp
                    <flux:card>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-800/30 flex items-center justify-center">
                                <flux:icon.academic-cap class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <flux:heading size="sm">{{ __('Admission Offer') }}</flux:heading>
                                <p class="text-xs text-zinc-500">{{ __('Congratulations!') }}</p>
                            </div>
                        </div>

                        <flux:button variant="primary" class="w-full mb-3" icon="document-text"
                            href="{{ route('applicant.admission-letter', $applicant) }}" target="_blank">
                            @if($applicant->enrolled_at)
                            {{ __('Print Offer of Admission') }}
                            @else
                            {{ __('Print Admission Notification') }}
                            @endif
                        </flux:button>

                        @if($admissionInvoice)
                        <flux:button variant="subtle" class="w-full mb-3" icon="printer"
                            href="{{ route('applicant.invoice.print', $admissionInvoice) }}" target="_blank">
                            {{ __('Print Invoice') }}
                        </flux:button>
                        @endif

                        @if($admissionInvoice)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{
                                    $admissionInvoice->invoice->title }}</span>
                                <flux:badge
                                    color="{{ match($admissionInvoice->status) { 'paid' => 'green', 'partial' => 'blue', default => 'warning' } }}">
                                    {{ ucfirst($admissionInvoice->status) }}
                                </flux:badge>
                            </div>
                            <div class="text-xs text-zinc-500 space-y-1 mb-3">
                                <div class="flex justify-between">
                                    <span>{{ __('Total:') }}</span>
                                    <span class="font-medium">₦{{ number_format($admissionInvoice->total_amount, 2)
                                        }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('Paid:') }}</span>
                                    <span class="font-medium text-green-600">₦{{
                                        number_format($admissionInvoice->amount_paid, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ __('Balance:') }}</span>
                                    <span class="font-bold text-red-600">₦{{ number_format($admissionInvoice->balance,
                                        2) }}</span>
                                </div>
                            </div>
                            @if(in_array($admissionInvoice->status, ['pending', 'partial']))
                            @php
                            $paidPct = $admissionInvoice->total_amount > 0
                                ? round(($admissionInvoice->amount_paid / $admissionInvoice->total_amount) * 100)
                                : 0;
                            @endphp
                            @if($admissionInvoice->status === 'partial')
                            <div class="mb-3">
                                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 mb-1">
                                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $paidPct }}%"></div>
                                </div>
                                <p class="text-xs text-zinc-500 text-right">{{ $paidPct }}% paid</p>
                            </div>
                            @endif
                            <flux:modal.trigger name="pay-admission-fee">
                                <flux:button variant="filled" class="w-full text-sm" :disabled="!$isActive">
                                    {{ __('Pay Admission Fees') }}
                                </flux:button>
                            </flux:modal.trigger>
                            @elseif($admissionInvoice->status === 'paid' && !$applicant->enrolled_at)
                            <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                <div class="flex items-center gap-2 mb-1">
                                    <flux:icon.clock class="w-4 h-4 text-blue-500 flex-shrink-0" />
                                    <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ __('Payment Received — Awaiting Enrollment') }}</span>
                                </div>
                                <p class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed">
                                    {{ __('Your fees are fully paid. The admissions office will complete your enrollment shortly. You will receive an email with your matric number and login credentials once enrolled.') }}
                                </p>
                            </div>
                            @elseif($applicant->enrolled_at)
                            <div class="mt-1 flex items-start gap-2 text-xs text-green-600 dark:text-green-400 font-medium">
                                <flux:icon.check-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                                <span>{{ __('Enrolled as a student. Check your email for login credentials.') }}</span>
                            </div>
                            @endif
                        </div>
                        @endif
                    </flux:card>
                    @endif
                </div>

                <!-- Main Content / Form completion -->
                <div class="md:col-span-2 space-y-6">
                    <flux:card>
                        @if($applicant->payment_status === 'paid')
                        <flux:heading size="lg" class="mb-4">{{ __('Academic Credentials') }}</flux:heading>

                        @if($applicant->admission_status === 'pending')
                        <p class="text-zinc-600 dark:text-zinc-400 mb-6">
                            {{ __('Please provide your O-Level exam results. This is required for your admission
                            review.') }}
                        </p>

                        <form wire:submit="submitCredentials" class="space-y-6">
                            @php
                            $examTypes = ['NECO', 'WAEC', 'NABTEB', 'NBAIS'];
                            $validGrades = ['A', 'A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];
                            @endphp

                            <flux:field>
                                <flux:label>{{ __('First Sitting') }}</flux:label>
                                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                    <div class="sm:col-span-1">
                                        <flux:select wire:model="sitting_1_exam_type" placeholder="{{ __('Type') }}"
                                            required>
                                            <flux:select.option value="">...</flux:select.option>
                                            @foreach($examTypes as $type)
                                            <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <flux:input wire:model="sitting_1_exam_number"
                                            placeholder="{{ __('Exam Number (e.g. 2510406620BE)') }}" required />
                                    </div>
                                    <div class="sm:col-span-1">
                                        <flux:input wire:model="sitting_1_exam_year" placeholder="{{ __('Year') }}"
                                            required />
                                    </div>
                                </div>
                                <flux:error name="sitting_1_exam_number" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Second Sitting (Optional)') }}</flux:label>
                                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                    <div class="sm:col-span-1">
                                        <flux:select wire:model="sitting_2_exam_type" placeholder="{{ __('Type') }}">
                                            <flux:select.option value="">...</flux:select.option>
                                            @foreach($examTypes as $type)
                                            <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <flux:input wire:model="sitting_2_exam_number"
                                            placeholder="{{ __('Exam Number') }}" />
                                    </div>
                                    <div class="sm:col-span-1">
                                        <flux:input wire:model="sitting_2_exam_year" placeholder="{{ __('Year') }}" />
                                    </div>
                                </div>
                                <flux:error name="sitting_2_exam_number" />
                            </flux:field>

                            <div class="space-y-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400 font-medium">{{ __('Core
                                    Subject Grades (Best of Sittings)') }}</flux:heading>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <flux:select wire:model="subject_english" :label="__('English')" required>
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                        <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_mathematics" :label="__('Mathematics')" required>
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                        <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_biology" :label="__('Biology')" required>
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                        <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_chemistry" :label="__('Chemistry')" required>
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                        <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_physics" :label="__('Physics')" required>
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                        <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>

                            <div class="space-y-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400 font-medium">{{
                                    __('Supporting Documents (PDF/JPG/PNG, Max 2MB)') }}</flux:heading>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>{{ __('Primary Certificate (Required)') }}</flux:label>
                                        <input type="file" wire:model="primary_document"
                                            class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-zinc-50 file:text-zinc-700 hover:file:bg-zinc-100" />
                                        <flux:error name="primary_document" />
                                    </flux:field>
                                    <flux:field>
                                        <flux:label>{{ __('Secondary Document (Optional)') }}</flux:label>
                                        <input type="file" wire:model="secondary_document"
                                            class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-zinc-50 file:text-zinc-700 hover:file:bg-zinc-100" />
                                        <flux:error name="secondary_document" />
                                    </flux:field>
                                    @if($applicant->applicationForm->category === 'retrainee')
                                    <flux:field>
                                        <flux:label>{{ __('Retrainee Document (Required)') }}</flux:label>
                                        <input type="file" wire:model="retrainee_document"
                                            class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-zinc-50 file:text-zinc-700 hover:file:bg-zinc-100" />
                                        <flux:error name="retrainee_document" />
                                    </flux:field>
                                    @endif
                                </div>
                                <div wire:loading wire:target="primary_document, secondary_document, retrainee_document"
                                    class="text-sm text-blue-600 font-medium">
                                    {{ __('Uploading...') }}
                                </div>
                            </div>

                            <div class="pt-4">
                                <flux:button type="submit" variant="primary" class="w-full" :disabled="!$isActive">
                                    {{ __('Submit Application for Review') }}
                                </flux:button>
                            </div>
                        </form>
                        @else
                        <div class="p-8 text-center sm:p-12">
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 text-green-600 mb-4">
                                <flux:icon.check-circle class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-medium text-zinc-900 dark:text-white">{{ __('Application Submitted')
                                }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Your application and academic credentials have been received. You will be
                                notified once a decision has been made.') }}
                            </p>

                            @if($applicant->admission_status === 'admitted')
                            <div
                                class="mt-8 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <strong class="block text-green-800 dark:text-green-300 font-medium mb-1">{{
                                    __('Congratulations!') }}</strong>
                                <span class="text-green-700 dark:text-green-400 text-sm">
                                    {{ __('You have been offered admission. Check your email for your admission letter
                                    and login credentials.') }}
                                </span>
                            </div>
                            @endif
                        </div>
                        @endif
                        @else
                        <div class="p-8 text-center sm:p-12">
                            <div
                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-100 text-orange-600 mb-4">
                                <flux:icon.clock class="w-6 h-6" />
                            </div>
                            <h3 class="text-lg font-medium text-zinc-900 dark:text-white">{{ __('Payment Verification
                                Pending') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Once your payment of ₦') }}{{ number_format($applicant->applicationForm->amount,
                                2) }}{{ __(' is verified, you will be able to complete your credentials here.') }}
                            </p>
                        </div>
                        @endif
                    </flux:card>
                </div>
            </div>
        </div>
    </div>

    <!-- Partial Payment Modal -->
    <flux:modal name="pay-admission-fee" class="md:w-[400px]">
        <form wire:submit="payAdmissionFee">
            <flux:heading size="lg" class="mb-4">{{ __('Pay Admission Fees') }}</flux:heading>

            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-6 leading-relaxed">
                {{ __('You can pay the full remaining balance or opt for a partial payment. To complete your enrollment,
                a minimum payment of 50% of the total invoice is required.') }}
            </p>

            <flux:field class="mb-6">
                <flux:label>{{ __('Amount to Pay (₦)') }}</flux:label>
                <flux:input wire:model="partial_payment_amount" type="number" step="0.01" min="1"
                    placeholder="{{ __('Leave blank to pay full balance') }}" />
                <flux:error name="partial_payment_amount" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Proceed to Payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>