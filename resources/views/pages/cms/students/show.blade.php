<?php

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Services\GradingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Student Profile')] class extends Component
{
    public Student $student;

    public string $newStatus = '';

    public function mount(Student $student): void
    {
        $this->student = $student;
        $this->newStatus = $student->status;
    }

    public function updateStatus(): void
    {
        $this->validate([
            'newStatus' => 'required|in:active,graduated,suspended,withdrawn',
        ]);

        $this->student->update(['status' => $this->newStatus]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Student status updated to '.ucfirst($this->newStatus).'.',
        ]);

        $this->js('$flux.modal("change-status").close()');
    }

    public function with(GradingService $gradingService): array
    {
        $activeSession = AcademicSession::where('status', 'active')->first();
        $invoices = StudentInvoice::where('student_id', $this->student->id)->with('invoice.academicSession')->get();

        return [
            'cgpa' => $gradingService->computeCgpa($this->student),
            'attendance' => $this->student->getAttendancePercentage(),
            'pendingBalance' => $invoices->whereIn('status', ['unpaid', 'partial'])->sum('balance'),
            'registrationStatus' => $this->student->courseRegistrations()->whereHas('academicSession', fn ($q) => $q->where('status', 'active'))->exists() ? 'Registered' : 'Not Registered',
            'unitsEarned' => $this->student->results()->with('course')->get()->sum(fn ($r) => $r->course->credit_unit ?? 0),
            'currentLevel' => $activeSession ? $this->student->currentLevel($activeSession) : '—',
            'institutionLogo' => $this->student->institution->logo_path ? Storage::url($this->student->institution->logo_path) : null,
            'studentInvoices' => $invoices->sortByDesc('created_at'),
            'statusColorMap' => [
                'active' => 'green',
                'graduated' => 'indigo',
                'suspended' => 'orange',
                'withdrawn' => 'red',
            ],
            'badgeVariantMap' => [
                'paid' => 'success',
                'partial' => 'warning',
                'unpaid' => 'danger',
                'cancelled' => 'neutral',
            ],
        ];
    }
}; ?>

<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="flex items-center gap-6">
            <div class="relative group">
                <div class="h-24 w-24 rounded-2xl bg-zinc-100 dark:bg-zinc-900 border-2 border-zinc-200 dark:border-zinc-700 overflow-hidden flex items-center justify-center shadow-inner">
                    @if ($student->photo_path)
                        <img src="{{ $student->photo_url }}" class="h-full w-full object-cover">
                    @else
                        <flux:icon icon="user" class="size-12 text-zinc-300" />
                    @endif
                </div>
            </div>
            
            <div class="space-y-1">
                <flux:heading size="xl" class="font-black">{{ $student->full_name }}</flux:heading>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-mono text-sm font-bold text-zinc-500 uppercase tracking-tighter bg-zinc-100 dark:bg-zinc-900 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                        {{ $student->matric_number }}
                    </span>
                    <flux:badge :color="$statusColorMap[$student->status] ?? 'zinc'" inset="top bottom" size="sm" class="font-bold">
                        {{ ucfirst($student->status) }}
                    </flux:badge>
                </div>
                <p class="text-sm text-zinc-500 font-medium">{{ $student->program->name }} • {{ $student->program->department->name }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button icon="pencil-square" variant="ghost" :href="route('cms.students.edit', $student)" wire:navigate>{{ __('Edit Profile') }}</flux:button>
            <flux:button icon="arrows-right-left" variant="primary" x-on:click="$flux.modal('change-status').show()">{{ __('Change Status') }}</flux:button>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <flux:card class="relative overflow-hidden group border-none bg-blue-600">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.academic-cap class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-blue-200 uppercase tracking-widest">{{ __('CGPA') }}</div>
                <div class="text-4xl font-black text-white">
                    {{ number_format($cgpa, 2) }}
                </div>
                <div class="text-[10px] text-blue-100 font-bold">{{ $unitsEarned }} {{ __('Units Earned') }}</div>
            </div>
        </flux:card>

        <flux:card class="relative overflow-hidden group border-none bg-emerald-600">
            <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.presentation-chart-line class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-emerald-100 uppercase tracking-widest">{{ __('Attendance') }}</div>
                <div class="text-4xl font-black text-white">{{ $attendance }}%</div>
                <div class="text-[10px] text-emerald-50 font-bold uppercase">{{ __('Overall Participation') }}</div>
            </div>
        </flux:card>

        <flux:card class="relative overflow-hidden group border-none bg-zinc-900 dark:bg-zinc-800">
             <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.check-badge class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{{ __('Reg. Status') }}</div>
                <div class="flex items-center gap-2">
                    <div class="text-3xl font-black text-white">{{ $currentLevel }}L</div>
                    <div class="size-2 rounded-full {{ $registrationStatus === 'Registered' ? 'bg-green-400' : 'bg-red-400 animate-pulse' }}"></div>
                </div>
                <div class="text-[10px] font-bold {{ $registrationStatus === 'Registered' ? 'text-green-400' : 'text-red-400' }} uppercase">{{ $registrationStatus }}</div>
            </div>
        </flux:card>

        <flux:card class="relative overflow-hidden group border-none bg-rose-600">
             <div class="absolute right-[-10%] top-[-10%] opacity-10 group-hover:scale-110 transition-transform">
                <flux:icon.banknotes class="size-24 text-white" />
            </div>
            <div class="space-y-1 relative z-10">
                <div class="text-[10px] font-black text-rose-100 uppercase tracking-widest">{{ __('Balance') }}</div>
                <div class="text-3xl font-black text-white">₦{{ number_format($pendingBalance, 2) }}</div>
                <div class="text-[10px] text-rose-50 font-bold uppercase">{{ __('Outstanding Fees') }}</div>
            </div>
        </flux:card>
    </div>

    {{-- Details Sections --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Biodata --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:card class="space-y-6">
                <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <flux:icon.user-circle class="size-6 text-zinc-400" />
                    <flux:heading size="lg">{{ __('Biodata & Personal Info') }}</flux:heading>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Email Address') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $student->email }}</p>
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Phone Number') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $student->phone ?? '—' }}</p>
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Gender') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ ucfirst($student->gender) ?? '—' }}</p>
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Date of Birth') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $student->date_of_birth?->format('F d, Y') ?? '—' }}</p>
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Origin') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $student->lga ?? '—' }}, {{ $student->state ?? '—' }}</p>
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-[10px] font-black uppercase text-zinc-400 tracking-wider">{{ __('Blood Group') }}</flux:label>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $student->blood_group ?? '—' }}</p>
                    </flux:field>
                </div>
            </flux:card>

            {{-- Invoices & Payments --}}
            <flux:card class="space-y-6" id="invoice-history">
                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <div class="flex items-center gap-3">
                        <flux:icon.credit-card class="size-6 text-zinc-400" />
                        <flux:heading size="lg">{{ __('Invoices & Payment History') }}</flux:heading>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="py-3 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-[10px] tracking-wider">Invoice</th>
                                <th class="py-3 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-[10px] tracking-wider text-right">Total</th>
                                <th class="py-3 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-[10px] tracking-wider text-right">Paid</th>
                                <th class="py-3 font-semibold text-zinc-900 dark:text-zinc-100 uppercase text-[10px] tracking-wider">Status</th>
                                <th class="py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($studentInvoices as $item)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">
                                    <td class="py-3">
                                        <flux:text weight="medium" class="text-sm">{{ $item->invoice->title }}</flux:text>
                                        <flux:text size="xs" class="text-zinc-500 uppercase">{{ $item->invoice->academicSession->name }}</flux:text>
                                    </td>
                                    <td class="py-3 text-right text-sm">₦{{ number_format($item->total_amount, 2) }}</td>
                                    <td class="py-3 text-right text-sm font-bold text-green-600">₦{{ number_format($item->amount_paid, 2) }}</td>
                                    <td class="py-3">
                                        <flux:badge size="sm" :variant="$badgeVariantMap[$item->status] ?? 'zinc'">{{ ucfirst($item->status) }}</flux:badge>
                                    </td>
                                    <td class="py-3 text-right">
                                        <flux:button variant="ghost" size="xs" icon="printer" :href="route('cms.invoices.print', $item)" target="_blank" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-zinc-500 italic text-sm">{{ __('No invoices recorded.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>

            {{-- Credentials / O'Level Results --}}
            <flux:card class="space-y-6">
                <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                    <flux:icon.document-check class="size-6 text-zinc-400" />
                    <flux:heading size="lg">{{ __('Education Credentials') }}</flux:heading>
                </div>

                <div class="space-y-6">
                    @if($student->sitting_1_exam_type)
                        <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:heading size="sm" class="mb-4 text-zinc-500 uppercase font-black tracking-widest text-[10px]">
                                {{ $student->sitting_1_exam_type }} ({{ $student->sitting_1_exam_year }}) - #{{ $student->sitting_1_exam_number }}
                            </flux:heading>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-zinc-400 uppercase mb-1">English</div>
                                    <div class="font-black text-zinc-900 dark:text-white">{{ $student->subject_english }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-zinc-400 uppercase mb-1">Maths</div>
                                    <div class="font-black text-zinc-900 dark:text-white">{{ $student->subject_mathematics }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-zinc-400 uppercase mb-1">Biology</div>
                                    <div class="font-black text-zinc-900 dark:text-white">{{ $student->subject_biology }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-zinc-400 uppercase mb-1">Chemistry</div>
                                    <div class="font-black text-zinc-900 dark:text-white">{{ $student->subject_chemistry }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] font-black text-zinc-400 uppercase mb-1">Physics</div>
                                    <div class="font-black text-zinc-900 dark:text-white">{{ $student->subject_physics }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($student->sitting_2_exam_type)
                         <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:heading size="sm" class="mb-4 text-zinc-500 uppercase font-black tracking-widest text-[10px]">
                                Second Sitting: {{ $student->sitting_2_exam_type }} ({{ $student->sitting_2_exam_year }})
                            </flux:heading>
                            <p class="text-xs text-zinc-500">{{ __('Verified secondary sitting credentials.') }}</p>
                        </div>
                    @endif
                    
                    @if(!$student->sitting_1_exam_type && !$student->sitting_2_exam_type)
                        <div class="py-8 text-center text-zinc-400 italic text-sm">
                            {{ __('No educational credentials on file.') }}
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

        {{-- Academic Profile & Logo --}}
        <div class="space-y-6">
            <flux:card class="space-y-6 flex flex-col items-center text-center">
                @if($institutionLogo)
                    <img src="{{ $institutionLogo }}" class="h-20 w-auto grayscale opacity-50 contrast-125 mb-2">
                @endif
                
                <div class="space-y-1">
                    <flux:heading size="md" class="font-black">{{ $student->institution->name }}</flux:heading>
                    <flux:subheading class="uppercase font-bold tracking-widest text-[10px]">{{ $student->institution->acronym }}</flux:subheading>
                </div>

                <div class="w-full space-y-4 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest text-[10px]">{{ __('Admission Year') }}</span>
                        <span class="font-black text-zinc-900 dark:text-white">{{ $student->admission_year }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest text-[10px]">{{ __('Entry Level') }}</span>
                        <span class="font-black text-zinc-900 dark:text-white">{{ $student->entry_level }}L</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest text-[10px]">{{ __('Current Level') }}</span>
                        <span class="font-black text-blue-600">{{ $currentLevel }}L</span>
                    </div>
                     <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500 font-bold uppercase tracking-widest text-[10px]">{{ __('Profile Progress') }}</span>
                        <flux:badge color="zinc" size="sm" inset="top bottom">{{ $student->completion_percentage }}%</flux:badge>
                    </div>
                </div>
            </flux:card>

            {{-- Quick Links / Actions --}}
            <flux:card class="space-y-4">
                <flux:heading size="sm" class="uppercase font-black text-[10px] tracking-widest text-zinc-400 mb-2">{{ __('Quick Actions') }}</flux:heading>
                
                <flux:navlist>
                    <flux:navlist.item icon="arrow-path" :href="route('cms.students.registration', ['student_id' => $student->id])" wire:navigate>{{ __('Course Registration') }}</flux:navlist.item>
                    <flux:navlist.item icon="credit-card" href="#invoice-history">{{ __('Manage Invoices') }}</flux:navlist.item>
                    <flux:navlist.item icon="chart-bar" href="#">{{ __('View Transcript') }}</flux:navlist.item>
                    <flux:navlist.item icon="printer" href="#">{{ __('ID Card Request') }}</flux:navlist.item>
                </flux:navlist>
            </flux:card>
        </div>
    </div>

    {{-- Status Modal --}}
    <flux:modal name="change-status" variant="filled" class="min-w-[22rem]">
        <form wire:submit="updateStatus" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change Student Status') }}</flux:heading>
                <flux:subheading>{{ __('Modify the current administrative status of :name.', ['name' => $student->full_name]) }}</flux:subheading>
            </div>

            <flux:select wire:model="newStatus" :label="__('New Status')">
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="graduated">{{ __('Graduated') }}</flux:select.option>
                <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
                <flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option>
            </flux:select>
            <flux:error name="newStatus" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Update Status') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
