<?php

use App\Models\Attendance;
use App\Models\AttendancePayment;
use App\Models\Institution;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Manage Lecturer Payments')] class extends Component {
    use WithPagination;

    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $payment_status = '';

    public bool $is_processing = false;
    public ?int $editing_staff_id = null;
    public float $new_rate = 0;

    #[Url]
    public ?int $selected_institution_id = null;

    public function mount(): void
    {
        Gate::authorize('manage_attendance_payments');

        if (!$this->month) {
            $this->month = date('n');
        }
        if (!$this->year) {
            $this->year = date('Y');
        }

        if (!$this->selected_institution_id && auth()->user()->institution_id) {
            $this->selected_institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentStatus(): void
    {
        $this->resetPage();
    }

    public function getStaffPaymentsProperty()
    {
        $institutionId = $this->selected_institution_id ?? auth()->user()->institution_id;

        if (!$institutionId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        $query = Staff::where('institution_id', $institutionId)
            ->where('role_id', 4) // 4 is the Lecturer role ID
            ->where('status', 'active');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%' . $this->search . '%')
                  ->orWhere('last_name', 'like', '%' . $this->search . '%')
                  ->orWhere('staff_number', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->payment_status) {
            if ($this->payment_status === 'unprocessed') {
                $query->whereDoesntHave('attendancePayments', function ($q) {
                    $q->where('month', $this->month)
                      ->where('year', $this->year);
                });
            } else {
                $query->whereHas('attendancePayments', function ($q) {
                    $q->where('month', $this->month)
                      ->where('year', $this->year)
                      ->where('status', $this->payment_status);
                });
            }
        }

        return $query->paginate(10)
            ->through(function ($staff) {
                // Find existing payment record
                $payment = AttendancePayment::where([
                    'staff_id' => $staff->id,
                    'month' => $this->month,
                    'year' => $this->year,
                ])->first();

                // Calculate actual contacts from attendances table
                $contacts = Attendance::whereMonth('date', $this->month)
                    ->whereYear('date', $this->year)
                    ->where('status', 'submitted')
                    ->where('is_combined_child', false)
                    ->whereHas('courseAllocation', function ($q) use ($staff) {
                        $q->where('user_id', User::where('email', $staff->email)->value('id'));
                    })
                    ->count();

                $rate = $staff->attendance_allowance ?? 0;

                return [
                    'staff' => $staff,
                    'payment' => $payment,
                    'calculated_contacts' => $contacts,
                    'current_rate' => $rate,
                    'calculated_total' => $contacts * $rate,
                ];
            });
    }

    public function generatePayment(int $staffId, int $contacts, float $rate): void
    {
        $staff = Staff::findOrFail($staffId);
        $institutionId = $staff->institution_id;

        AttendancePayment::updateOrCreate(
            [
                'staff_id' => $staffId,
                'month' => $this->month,
                'year' => $this->year,
            ],
            [
                'institution_id' => $institutionId,
                'contacts_count' => $contacts,
                'allowance_rate' => $rate,
                'total_amount' => $contacts * $rate,
                'status' => 'pending',
            ]
        );

        $this->dispatch('notify', [
            'message' => __('Attendance submitted successfully! Email notifications sent to stakeholders.'),
            'variant' => 'success',
        ]);
    }

    public function markAsPaid(int $paymentId): void
    {
        $payment = AttendancePayment::findOrFail($paymentId);
        $payment->update([
            'status' => 'paid',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $this->dispatch('notify', [
            'message' => __('Payment marked as paid.'),
            'variant' => 'success',
        ]);
    }

    public function startEditingRate(int $staffId, float $currentRate): void
    {
        $this->editing_staff_id = $staffId;
        $this->new_rate = $currentRate;
    }

    public function cancelEditingRate(): void
    {
        $this->editing_staff_id = null;
        $this->new_rate = 0;
    }

    public function updateRate(): void
    {
        Gate::authorize('manage_attendance_payments');

        $staff = Staff::findOrFail($this->editing_staff_id);
        $staff->update([
            'attendance_allowance' => $this->new_rate,
        ]);

        $this->editing_staff_id = null;
        $this->new_rate = 0;

        $this->dispatch('notify', [
            'message' => __('Lecturer allowance rate updated.'),
            'variant' => 'success',
        ]);
    }

    public function render(): View
    {
        return view('pages::cms.attendance.manage-payments', [
            'staffPayments' => $this->staff_payments,
            'institutions' => auth()->user()->hasRole('Super Admin')
                ? Institution::query()->where('status', 'active')->orderBy('name')->get()
                : [],
            'months' => [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December',
            ],
            'years' => range(date('Y'), date('Y') - 2),
        ]);
    }
}; ?>

<div class="mx-auto max-w-7xl">
    <div class="mb-4 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search staff...') }}" class="w-full md:w-64" clearable />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="selected_institution_id" class="w-full md:w-64"
                    :placeholder="__('Select Institution...')">
                    <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="payment_status" class="w-full sm:w-36" :placeholder="__('All Statuses')">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="unprocessed">{{ __('Unprocessed') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="month" class="w-full sm:w-36">
                @foreach ($months as $num => $name)
                    <flux:select.option :value="$num">{{ __($name) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="year" class="w-full sm:w-28">
                @foreach ($years as $y)
                    <flux:select.option :value="$y">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$staffPayments">
        <flux:table.columns>
            <flux:table.column>{{ __('Staff Member') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('Bank Details') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Contacts') }}</flux:table.column>
            <flux:table.column align="right">{{ __('Rate / Total') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
            <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($staffPayments as $item)
                <flux:table.row>
                    <flux:table.cell>
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $item['staff']->first_name }}
                                    {{ $item['staff']->last_name }}</div>
                                <div class="text-xs text-zinc-500 uppercase">
                                    {{ $item['staff']->hodDepartments->first()?->name ?? __('Lecturer') }}
                                </div>
                                <div class="text-[10px] text-zinc-400 italic">{{ $item['staff']->staff_number }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                                @if($item['staff']->bank_name && $item['staff']->account_number)
                                    <div class="text-xs font-medium text-zinc-900 dark:text-white">
                                        {{ $item['staff']->bank_name }}</div>
                                    <div class="font-mono text-xs text-blue-600 dark:text-blue-400">
                                        {{ $item['staff']->account_number }}</div>
                                    <div class="text-[10px] text-zinc-500 uppercase">{{ $item['staff']->account_name }}</div>
                                @else
                                    <flux:badge color="amber" size="sm" class="flex gap-1 items-center">
                                        <flux:icon.exclamation-triangle class="size-3" />
                                        {{ __('No Bank Details') }}
                                    </flux:badge>
                                @endif
                    </flux:table.cell>
                    <flux:table.cell align="center">
                                <div class="text-lg font-black text-zinc-900 dark:text-white">
                                    {{ $item['payment'] ? $item['payment']->contacts_count : $item['calculated_contacts'] }}
                                </div>
                                @if($item['payment'] && $item['payment']->contacts_count != $item['calculated_contacts'])
                                    <div class="text-[10px] text-amber-600 font-bold">
                                        {{ __('Live:') }} {{ $item['calculated_contacts'] }}
                                    </div>
                                @endif
                    </flux:table.cell>
                    <flux:table.cell align="right">
                                @if($editing_staff_id === $item['staff']->id)
                                    <div class="flex items-center justify-end gap-1 mb-1">
                                        <flux:input wire:model="new_rate" type="number" step="0.01" size="sm" class="w-28" />
                                        <flux:button size="xs" variant="ghost" wire:click="updateRate" icon="check" />
                                        <flux:button size="xs" variant="ghost" wire:click="cancelEditingRate" icon="x-mark" />
                                    </div>
                                @else
                                    <div class="flex flex-col items-end">
                                        <div class="flex items-center justify-end gap-1 group">
                                            <div class="text-xs text-zinc-500">₦{{ number_format($item['current_rate'], 2) }} /
                                                contact</div>
                                            @if(auth()->user()->hasAnyRole(['Super Admin', 'Institutional Admin', 'Accountant']))
                                                <button
                                                    wire:click="startEditingRate({{ $item['staff']->id }}, {{ $item['current_rate'] }})"
                                                    class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <flux:icon.pencil-square class="size-3 text-zinc-400 hover:text-zinc-600" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                <div class="text-lg font-black text-zinc-900 dark:text-white">
                                    ₦{{ number_format($item['payment'] ? $item['payment']->total_amount : $item['calculated_total'], 2) }}
                                </div>
                    </flux:table.cell>
                    <flux:table.cell align="center">
                                @if($item['payment'])
                                    <flux:badge :color="match($item['payment']->status) {
                                                'paid' => 'green',
                                                'approved' => 'blue',
                                                'cancelled' => 'red',
                                                default => 'zinc'
                                            }" size="sm">
                                        {{ ucfirst($item['payment']->status) }}
                                    </flux:badge>
                                    @if($item['payment']->status === 'paid')
                                        <div class="text-[9px] text-zinc-400 mt-1 uppercase">
                                            {{ $item['payment']->processed_at->format('M d, Y') }}</div>
                                    @endif
                                @else
                                    <flux:badge color="zinc" size="sm" variant="outline">{{ __('Unprocessed') }}</flux:badge>
                                @endif
                    </flux:table.cell>
                    <flux:table.cell align="right">
                                <div class="flex justify-end gap-2">
                                    @if(!$item['payment'] || ($item['payment']->status === 'pending' && ($item['payment']->contacts_count != $item['calculated_contacts'] || $item['payment']->allowance_rate != $item['current_rate'])))
                                        <flux:button size="xs" variant="ghost"
                                            wire:click="generatePayment({{ $item['staff']->id }}, {{ $item['calculated_contacts'] }}, {{ $item['current_rate'] }})">
                                            {{ $item['payment'] ? __('Update') : __('Generate') }}
                                        </flux:button>
                                    @endif

                                    @if($item['payment'] && $item['payment']->status !== 'paid')
                                        <flux:button size="xs" variant="primary"
                                            wire:click="markAsPaid({{ $item['payment']->id }})"
                                            :disabled="!$item['staff']->account_number">
                                            {{ __('Mark as Paid') }}
                                        </flux:button>
                                    @endif
                                </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-12">
                        <flux:icon.users class="mx-auto size-8 mb-3 opacity-20" />
                        <p>{{ __('No staff members found for this institution.') }}</p>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>