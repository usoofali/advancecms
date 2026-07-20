<?php

use App\Models\Student;
use App\Models\Staff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ID Card Verification')] #[Layout('layouts.guest')] class extends Component
{
    public string $idNumber = '';

    public mixed $profile = null;
    public string $type = '';
    public bool $isValid = false;

    public function mount(string $idNumber): void
    {
        $decodedNumber = urldecode($idNumber);
        $this->idNumber = $decodedNumber;

        // Try to find a student
        $student = Student::with(['program.department.institution', 'institution', 'user'])
            ->where('matric_number', $decodedNumber)
            ->first();

        if ($student) {
            $this->profile = $student;
            $this->type = 'student';
            $this->isValid = true;
            return;
        }

        // Try to find a staff
        $staff = Staff::with(['institution', 'user'])
            ->where('staff_number', $decodedNumber)
            ->first();

        if ($staff) {
            $this->profile = $staff;
            $this->type = 'staff';
            $this->isValid = true;
        }
    }
}; ?>

<div class="min-h-screen bg-zinc-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        @php
            $institution = null;
            if ($isValid) {
                if ($type === 'student') {
                    $institution = $profile->institution ?? $profile->program?->department?->institution;
                } else {
                    $institution = $profile->institution;
                }
            }
        @endphp

        @if ($institution && $institution->logo_url)
            <img src="{{ $institution->logo_url }}" alt="Logo" class="mx-auto h-16 w-auto mb-4 border border-zinc-200 rounded-full p-2 bg-white">
        @else
            <div class="mx-auto h-16 w-16 bg-zinc-800 rounded-full flex items-center justify-center mb-4 shadow-sm">
                <flux:icon.identification class="size-8 text-white" />
            </div>
        @endif
        
        <h2 class="mt-2 text-center text-3xl font-extrabold text-zinc-900 uppercase tracking-tight">
            ID Verification
        </h2>
        <p class="mt-2 text-center text-sm text-zinc-600">
            Verify the authenticity of an official identity card.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-xl">
        <div class="bg-white py-8 px-4 shadow sm:rounded-xl sm:px-10 border border-zinc-200">
            @if ($isValid && $profile)
                @php
                    $isActive = $profile->status === 'active';
                    $user = $profile->user;
                    $name = $user?->name ?? trim(($profile->first_name ?? '').' '.($profile->last_name ?? ''));
                    $photoUrl = $profile->photo_path ? asset('storage/'.$profile->photo_path) : null;
                @endphp

                <div class="flex flex-col items-center border-b border-zinc-100 pb-6 mb-6">
                    @if($isActive)
                        <div class="h-16 w-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <flux:icon.check-badge class="size-10 text-green-600" />
                        </div>
                        <h3 class="text-xl font-bold text-green-700 uppercase">Valid Identity</h3>
                        <p class="text-sm text-zinc-500 mt-1 text-center">This ID card belongs to an active {{ $type === 'student' ? 'student' : 'staff member' }}.</p>
                    @else
                        <div class="h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <flux:icon.exclamation-triangle class="size-10 text-red-600" />
                        </div>
                        <h3 class="text-xl font-bold text-red-700 uppercase">Inactive Status</h3>
                        <p class="text-sm text-zinc-500 mt-1 text-center">This individual is currently listed as <strong>{{ strtoupper($profile->status) }}</strong>.</p>
                    @endif
                </div>

                <div class="flex flex-col items-center mb-6">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Photo" class="h-32 w-32 object-cover rounded-xl shadow-sm border-4 border-white ring-1 ring-zinc-200">
                    @else
                        <div class="h-32 w-32 bg-zinc-100 rounded-xl flex items-center justify-center shadow-sm border border-zinc-200">
                            <flux:icon.user class="size-12 text-zinc-400" />
                        </div>
                    @endif
                    <h4 class="text-xl font-bold text-zinc-900 mt-4 text-center">{{ $name }}</h4>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 mt-2 uppercase tracking-wide border border-blue-200">
                        {{ $type === 'student' ? 'Student' : 'Staff Member' }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 gap-y-4 sm:grid-cols-2 bg-zinc-50 p-6 rounded-xl border border-zinc-100">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold text-zinc-500 uppercase tracking-wider">ID Number</dt>
                        <dd class="mt-1 text-sm text-zinc-900 font-mono font-bold bg-white p-2 rounded border border-zinc-200 inline-block">
                            {{ $idNumber }}
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold text-zinc-500 uppercase tracking-wider">
                            {{ $type === 'student' ? 'Department / Program' : 'Designation' }}
                        </dt>
                        <dd class="mt-1 text-base font-semibold text-zinc-900">
                            {{ $type === 'student' ? ($profile->program?->name ?? 'N/A') : ($profile->designation ?? 'N/A') }}
                        </dd>
                    </div>
                </dl>
                
            @else
                <div class="flex flex-col items-center py-6">
                    <div class="h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <flux:icon.x-circle class="size-10 text-red-600" />
                    </div>
                    <h3 class="text-xl font-bold text-red-700 text-center">Invalid ID Card</h3>
                    <p class="text-sm text-zinc-500 mt-2 text-center max-w-sm">
                        We could not find any official records matching the provided ID number. This card may be fraudulent or the number was mistyped.
                    </p>
                    
                    <div class="mt-6 w-full bg-red-50 border border-red-100 rounded-lg p-4 text-center">
                        <span class="text-xs font-bold text-red-800 uppercase tracking-wider block mb-1">Queried ID Number:</span>
                        <span class="font-mono text-sm text-red-900">{{ $idNumber }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <div class="mt-8 text-center text-xs text-zinc-400">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </div>
</div>
