@php
$user = $this->user;
$profile = $this->profile;
@endphp

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')"
        :subheading="__('Your personal and academic/professional information.')">
        <div class="space-y-8 my-6">
            <!-- Profile Completion Progress -->
            @if($user->hasRole('Student'))
            <div class="space-y-2">
                <div class="flex justify-between items-end">
                    <flux:heading size="sm">{{ __('Profile Completion') }}</flux:heading>
                    <span class="text-sm font-bold text-blue-600">{{ $profile->completion_percentage }}%</span>
                </div>
                <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden border border-zinc-200/50 dark:border-zinc-700/50">
                    <div class="bg-blue-600 h-full transition-all duration-1000" style="width: {{ $profile->completion_percentage }}%"></div>
                </div>
                @if($profile->completion_percentage < 100)
                <p class="text-[10px] text-zinc-400 italic">{{ __('Complete your profile to ensure all academic services are available.') }}</p>
                @endif
            </div>
            @endif

            <!-- Header Identity Section with Photo Upload -->
            <div class="flex flex-col md:flex-row items-center gap-6 p-6 bg-zinc-50 dark:bg-zinc-800/50 rounded-2xl border border-zinc-200/50 dark:border-zinc-700/50">
                <div class="relative group">
                    <div class="size-24 rounded-2xl overflow-hidden border-4 border-white dark:border-zinc-700 shadow-md bg-zinc-200 dark:bg-zinc-800">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover">
                        @elseif ($profile && $profile->photo_path)
                            <img src="{{ asset('storage/' . $profile->photo_path) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-blue-600 text-white text-3xl font-bold">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                        @endif
                        
                        <div wire:loading wire:target="photo" class="absolute inset-0 bg-black/60 flex items-center justify-center rounded-2xl">
                            <flux:icon.arrow-path class="size-8 text-white animate-spin" />
                        </div>
                    </div>
                    
                    <label class="absolute -bottom-2 -right-2 p-2 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg cursor-pointer transition-transform hover:scale-110">
                        <flux:icon.camera class="size-4" />
                        <input type="file" wire:model="photo" class="hidden" accept="image/*" />
                    </label>
                </div>

                <div class="text-center md:text-left flex-1">
                    <flux:heading size="xl" class="font-black">{{ $user->name }}</flux:heading>
                    <flux:subheading class="flex items-center justify-center md:justify-start gap-2">
                        <flux:icon.envelope class="size-3" />
                        {{ $user->email }}
                    </flux:subheading>
                </div>

                @if($profile)
                <div class="flex flex-col items-center md:items-end gap-2">
                    <flux:badge :color="$profile->status === 'active' ? 'green' : 'zinc'" size="sm" class="px-3 py-1">
                        {{ $profile->status ? ucfirst($profile->status) : 'Active' }}
                    </flux:badge>
                    <flux:text size="xs" class="text-zinc-500 uppercase tracking-widest font-bold">{{ $user->hasRole('Student') ? __('Student Account') : __('Staff Account') }}</flux:text>
                </div>
                @endif
            </div>

            <form wire:submit="updateProfile" class="space-y-8">
                <div class="grid grid-cols-1 gap-8 max-w-2xl">
                    <!-- Administrative / Personal Details -->
                    <div class="space-y-6">
                        <div class="space-y-4">
                            <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Account & Identity') }}</flux:heading>
                            
                            @if($user->hasRole('Student'))
                            <flux:input :label="__('Matric/Student Number')" :value="$profile?->matric_number" readonly
                                variant="filled" icon="identification" />
                            <flux:input :label="__('Program')" :value="$profile?->program?->name" readonly variant="filled"
                                icon="academic-cap" />
                            <flux:input :label="__('Admission Year')" :value="$profile?->admission_year" readonly
                                variant="filled" icon="calendar" />
                            @elseif($user->hasRole('Staff'))
                            <flux:input :label="__('Staff Number')" :value="$profile?->staff_number" readonly
                                variant="filled" icon="identification" />
                            <flux:input :label="__('Designation')" :value="$profile?->designation" readonly variant="filled"
                                icon="briefcase" />
                            @endif

                            <flux:input :label="__('Institution')" :value="$user->institution?->name" readonly
                                variant="filled" icon="building-library" />
                        </div>

                        @if($user->hasRole('Student'))
                        <div class="space-y-4">
                            <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Personal Details') }}</flux:heading>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <flux:select wire:model="gender" :label="__('Gender')" icon="user">
                                    <flux:select.option value="">{{ __('Select...') }}</flux:select.option>
                                    <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                                    <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                                </flux:select>
                                
                                <flux:input wire:model="date_of_birth" type="date" :label="__('Date of Birth')" icon="calendar" />
                            </div>
                            <flux:select wire:model="blood_group" :label="__('Blood Group')" icon="heart">
                                <flux:select.option value="">{{ __('Select...') }}</flux:select.option>
                                @foreach(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $group)
                                    <flux:select.option :value="$group">{{ $group }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <flux:select wire:model="state" :label="__('State of Origin')" icon="map" searchable>
                                    <flux:select.option value="">{{ __('Select state...') }}</flux:select.option>
                                    @foreach([
                                        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River',
                                        'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano',
                                        'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun',
                                        'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'
                                    ] as $stateName)
                                        <flux:select.option :value="$stateName">{{ $stateName }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="lga" :label="__('LGA of Origin')" icon="map-pin" />
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Contact & Credentials -->
                    <div class="space-y-6">
                        <div class="space-y-4">
                            <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Security & Contact') }}</flux:heading>
                            <flux:input wire:model="phone" :label="__('Phone Number')" type="tel" icon="phone" placeholder="e.g. +234..." />

                            @if($user->hasRole('Staff'))
                            <div class="pt-4 space-y-4 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Personal Details') }}</flux:heading>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <flux:select wire:model="gender" :label="__('Gender')" icon="user">
                                        <flux:select.option value="">{{ __('Select...') }}</flux:select.option>
                                        <flux:select.option value="male">{{ __('Male') }}</flux:select.option>
                                        <flux:select.option value="female">{{ __('Female') }}</flux:select.option>
                                    </flux:select>
                                    
                                    <flux:input wire:model="date_of_birth" type="date" :label="__('Date of Birth')" icon="calendar" />
                                </div>
                            </div>

                            <div class="pt-8 space-y-4 border-t border-zinc-100 dark:border-zinc-800">
                                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Bank Details (For Payments)') }}</flux:heading>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <flux:input wire:model="bank_name" :label="__('Bank Name')" placeholder="{{ __('e.g. Zenith Bank') }}" icon="building-library" />
                                    <flux:input wire:model="account_number" :label="__('Account Number')" placeholder="{{ __('10 Digits') }}" icon="credit-card" />
                                    <div class="md:col-span-2">
                                        <flux:input wire:model="account_name" :label="__('Account Name')" placeholder="{{ __('Official name on bank account') }}" icon="user-circle" />
                                    </div>
                                </div>
                                <flux:subheading size="xs" class="text-zinc-600 italic">{{ __('Ensure these details are correct to avoid payment delays.') }}</flux:subheading>
                            </div>
                            @endif
                        </div>

                        @if($user->hasRole('Student'))
                        <div class="space-y-4">
                            <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Academic Credentials') }}</flux:heading>
                            
                            @php 
                                $examTypes = ['NECO', 'WAEC', 'NABTEB', 'NBAIS'];
                                $validGrades = ['A', 'A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];
                            @endphp

                            <flux:field>
                                <flux:label>{{ __('First Sitting') }}</flux:label>
                                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                    <div class="sm:col-span-1">
                                        <flux:select wire:model="sitting_1_exam_type" placeholder="{{ __('Type') }}">
                                            @foreach($examTypes as $type)
                                                <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <flux:input wire:model="sitting_1_exam_number" placeholder="{{ __('Exam Number (e.g. 2510406620BE)') }}" />
                                    </div>
                                    <div class="sm:col-span-1">
                                        <flux:input wire:model="sitting_1_exam_year" placeholder="{{ __('Year') }}" />
                                    </div>
                                </div>
                                <flux:error name="sitting_1_exam_number" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Second Sitting (Optional)') }}</flux:label>
                                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                    <div class="sm:col-span-1">
                                        <flux:select wire:model="sitting_2_exam_type" placeholder="{{ __('Type') }}">
                                            @foreach($examTypes as $type)
                                                <flux:select.option :value="$type">{{ $type }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <flux:input wire:model="sitting_2_exam_number" placeholder="{{ __('Exam Number') }}" />
                                    </div>
                                    <div class="sm:col-span-1">
                                        <flux:input wire:model="sitting_2_exam_year" placeholder="{{ __('Year') }}" />
                                    </div>
                                </div>
                                <flux:error name="sitting_2_exam_number" />
                            </flux:field>

                            <div class="space-y-4">
                                <flux:heading size="xs" weight="semibold" class="text-zinc-400 uppercase tracking-widest">{{ __('Core Subject Grades') }}</flux:heading>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <flux:select wire:model="subject_english" :label="__('English')">
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_mathematics" :label="__('Mathematics')">
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_biology" :label="__('Biology')">
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_chemistry" :label="__('Chemistry')">
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="subject_physics" :label="__('Physics')">
                                        <flux:select.option value="">{{ __('Grade') }}</flux:select.option>
                                        @foreach($validGrades as $grade)
                                            <flux:select.option :value="$grade">{{ $grade }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end border-t border-zinc-100 dark:border-zinc-800 pt-6">
                    <flux:button variant="primary" type="submit" class="w-full md:w-auto" icon="check">
                        {{ __('Save Profile Changes') }}
                    </flux:button>
                </div>

                <x-action-message class="mt-2" on="profile-updated">
                    {{ __('Profile updated successfully.') }}
                </x-action-message>
            </form>

            <!-- Additional Context Section -->
            @if($user->hasRole('Student'))
            <div class="p-6 border-2 border-dashed border-zinc-100 dark:border-zinc-800 rounded-2xl">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon.information-circle class="size-6 text-blue-600" />
                    </div>
                    <div>
                        <flux:heading size="sm">Need to update official data?</flux:heading>
                        <flux:text size="sm" class="text-zinc-500 mt-1">If your name, matric number, or program
                            information is incorrect, please visit the Academic Registry for verification and official
                            updates.</flux:text>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>