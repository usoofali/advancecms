<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollStaffProfile;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Staff Pay Setup')] class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    // Edit Modal State
    public bool $showProfileModal = false;

    public ?int $editingStaffId = null;

    public string $editingStaffName = '';

    public ?int $prof_category_id = null;

    public ?int $prof_department_id = null;

    public string $prof_pay_type = '';

    public string $prof_contact_formula = '';

    public string $prof_base_salary = '0';

    public string $prof_custom_rate = '';

    public string $prof_bank_name = '';

    public string $prof_account_number = '';

    public string $prof_account_name = '';

    public string $prof_status = 'active';

    public function mount(): void
    {
        Gate::authorize('payroll.manage');

        $user = auth()->user();
        if (! $user->hasRole('Super Admin')) {
            $this->institution_id = $user->institution_id;
        } elseif (! $this->institution_id) {
            $this->institution_id = Institution::where('status', 'active')->value('id');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: Institution::where('status', 'active')->value('id'))
            : $user->institution_id;
    }

    public function openEditProfileModal(int $staffId): void
    {
        $instId = $this->resolveInstitutionId();
        $staff = Staff::with('payrollProfile')->where('institution_id', $instId)->findOrFail($staffId);
        $profile = $staff->payrollProfile;

        $this->editingStaffId = $staff->id;
        $this->editingStaffName = trim("{$staff->first_name} {$staff->last_name}")." ({$staff->staff_number})";
        $this->prof_category_id = $profile?->payroll_category_id;
        $this->prof_department_id = $profile?->department_id;
        $this->prof_pay_type = (string) ($profile?->pay_type ?? '');
        $this->prof_contact_formula = (string) ($profile?->contact_formula ?? '');
        $this->prof_base_salary = (string) ($profile?->base_salary ?? '0');
        $this->prof_custom_rate = $profile?->custom_rate !== null ? (string) $profile->custom_rate : '';
        $this->prof_bank_name = (string) ($profile?->bank_name ?: $staff->bank_name ?? '');
        $this->prof_account_number = (string) ($profile?->account_number ?: $staff->account_number ?? '');
        $this->prof_account_name = (string) ($profile?->account_name ?: $staff->account_name ?? '');
        $this->prof_status = $profile?->status ?? 'active';

        $this->showProfileModal = true;
    }

    public function saveProfile(): void
    {
        $instId = $this->resolveInstitutionId();
        if (! $instId || ! $this->editingStaffId) {
            return;
        }

        $this->validate([
            'prof_category_id' => 'nullable|integer',
            'prof_department_id' => 'nullable|integer',
            'prof_pay_type' => 'nullable|in:,fixed,contact_based',
            'prof_contact_formula' => 'nullable|in:,contact_x_credit_unit,hour_x_credit_unit,per_contact,per_hour',
            'prof_base_salary' => 'required|numeric|min:0',
            'prof_custom_rate' => 'nullable|numeric|min:0',
            'prof_status' => 'required|in:active,suspended',
        ]);

        $staff = Staff::where('institution_id', $instId)->findOrFail($this->editingStaffId);

        PayrollStaffProfile::updateOrCreate(
            ['institution_id' => $instId, 'staff_id' => $staff->id],
            [
                'payroll_category_id' => $this->prof_category_id ?: null,
                'department_id' => $this->prof_department_id ?: null,
                'pay_type' => $this->prof_pay_type ?: null,
                'contact_formula' => $this->prof_contact_formula ?: null,
                'base_salary' => (float) $this->prof_base_salary,
                'custom_rate' => $this->prof_custom_rate !== '' ? (float) $this->prof_custom_rate : null,
                'bank_name' => $this->prof_bank_name ?: null,
                'account_number' => $this->prof_account_number ?: null,
                'account_name' => $this->prof_account_name ?: null,
                'status' => $this->prof_status,
            ]
        );

        $this->showProfileModal = false;
        $this->dispatch('notify', title: __('Staff pay profile saved.'));
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        $defaultCategory = $instId
            ? PayrollCategory::where('institution_id', $instId)->where('is_default', true)->where('status', 'active')->first()
            : null;

        $staffQuery = Staff::query()
            ->with(['payrollProfile.category', 'payrollProfile.department', 'role'])
            ->where('institution_id', $instId ?? 0)
            ->where('status', 'active')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('staff_number', 'like', "%{$this->search}%");
                });
            })
            ->when($this->categoryFilter, function ($q) {
                $q->whereHas('payrollProfile', fn ($pq) => $pq->where('payroll_category_id', $this->categoryFilter));
            })
            ->orderBy('first_name');

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'staffList' => $staffQuery->paginate(15),
            'categories' => $instId ? PayrollCategory::where('institution_id', $instId)->where('status', 'active')->orderBy('name')->get() : collect(),
            'departments' => $instId ? Department::where('institution_id', $instId)->orderBy('name')->get() : collect(),
            'defaultCategory' => $defaultCategory,
            'formulas' => PayrollCategory::contactFormulaLabels(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Staff Pay Setup') }}</flux:heading>
            <flux:subheading>{{ __('Staff automatically inherit your institution/category defaults, or can be customized individually') }}</flux:subheading>
        </div>

        @if(auth()->user()->hasRole('Super Admin'))
            <flux:select wire:model.live="institution_id" class="w-full sm:w-60">
                @foreach($institutions as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search staff name or ID...')" clearable />

        <flux:select wire:model.live="categoryFilter">
            <flux:select.option value="">{{ __('All Staff Categories') }}</flux:select.option>
            @foreach($categories as $cat)
                <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Staff Member') }}</th>
                    <th class="px-4 py-3">{{ __('Category / Dept') }}</th>
                    <th class="px-4 py-3">{{ __('Pay Method') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Salary / Rate') }}</th>
                    <th class="px-4 py-3">{{ __('Bank Details') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                @forelse($staffList as $staff)
                    @php
                        $profile = $staff->payrollProfile;
                        $effType = $profile ? $profile->effectivePayType($defaultCategory) : ($defaultCategory?->pay_type ?? 'fixed');
                        $effRate = $profile ? $profile->effectiveRate($defaultCategory) : (float) ($defaultCategory?->default_rate ?? $staff->attendance_allowance ?? 0);
                        $isCustom = $profile?->hasCustomOverride() ?? false;
                        $bankName = $profile?->bank_name ?: $staff->bank_name;
                        $acctNo = $profile?->account_number ?: $staff->account_number;
                    @endphp
                    <tr wire:key="pay-staff-{{ $staff->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-900/20">
                        <td class="px-4 py-3.5">
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $staff->first_name }} {{ $staff->last_name }}</div>
                            <div class="text-xs font-mono text-zinc-500">{{ $staff->staff_number }} &bull; {{ $staff->role?->role_name ?? __('Staff') }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-xs">
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $profile?->category?->name ?? ($defaultCategory?->name ? $defaultCategory->name.' (Default)' : '—') }}
                            </div>
                            @if($profile?->department)
                                <div class="text-zinc-500">{{ $profile->department->name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <flux:badge size="sm" :color="$effType === 'fixed' ? 'emerald' : 'amber'">
                                    {{ $effType === 'fixed' ? __('Fixed Salary') : __('Per Contact / Unit') }}
                                </flux:badge>
                                @if($isCustom)
                                    <flux:badge size="sm" color="indigo">{{ __('Custom') }}</flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs font-bold">
                            @if($effType === 'fixed')
                                ₦{{ number_format((float) ($profile?->base_salary ?? 0), 2) }} <span class="font-normal text-zinc-400">/mo</span>
                            @else
                                ₦{{ number_format($effRate, 2) }} <span class="font-normal text-zinc-400">/rate</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-xs text-zinc-600 dark:text-zinc-400">
                            @if($acctNo)
                                <div>{{ $bankName }}</div>
                                <div class="font-mono">{{ $acctNo }}</div>
                            @else
                                <span class="text-zinc-400 italic">{{ __('Not set') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditProfileModal({{ $staff->id }})">
                                {{ __('Configure') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-zinc-500">{{ __('No active staff found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse($staffList as $staff)
            @php
                $profile = $staff->payrollProfile;
                $effType = $profile ? $profile->effectivePayType($defaultCategory) : ($defaultCategory?->pay_type ?? 'fixed');
                $effRate = $profile ? $profile->effectiveRate($defaultCategory) : (float) ($defaultCategory?->default_rate ?? $staff->attendance_allowance ?? 0);
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $staff->first_name }} {{ $staff->last_name }}</div>
                        <div class="text-xs font-mono text-zinc-500">{{ $staff->staff_number }}</div>
                    </div>
                    <flux:badge size="sm" :color="$effType === 'fixed' ? 'emerald' : 'amber'">
                        {{ $effType === 'fixed' ? __('Fixed') : __('Contact/Unit') }}
                    </flux:badge>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                    <span class="font-mono font-bold">
                        {{ $effType === 'fixed' ? '₦'.number_format((float) ($profile?->base_salary ?? 0), 2).'/mo' : '₦'.number_format($effRate, 2).'/rate' }}
                    </span>
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditProfileModal({{ $staff->id }})">
                        {{ __('Configure') }}
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                {{ __('No active staff found.') }}
            </div>
        @endforelse
    </div>

    <div>{{ $staffList->links() }}</div>

    {{-- Staff Profile Modal --}}
    <flux:modal wire:model.self="showProfileModal" class="max-w-lg w-full">
        <form wire:submit="saveProfile" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Configure Staff Pay') }}</flux:heading>
                <flux:subheading>{{ $editingStaffName }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:select wire:model="prof_category_id" :label="__('Staff Category')">
                    <flux:select.option value="">{{ __('Use Institution Default') }}</flux:select.option>
                    @foreach($categories as $cat)
                        <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="prof_department_id" :label="__('Department (Optional)')">
                    <flux:select.option value="">{{ __('Unassigned / General') }}</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ __('Individual Payment Setup (Optional Override)') }}</div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:select wire:model.live="prof_pay_type" :label="__('Pay Method Override')">
                        <flux:select.option value="">{{ __('Inherit from Category') }}</flux:select.option>
                        <flux:select.option value="fixed">{{ __('Fixed Monthly Salary') }}</flux:select.option>
                        <flux:select.option value="contact_based">{{ __('Per Contact / Credit Unit') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="prof_contact_formula" :label="__('Formula Override')">
                        <flux:select.option value="">{{ __('Inherit from Category') }}</flux:select.option>
                        @foreach($formulas as $key => $label)
                            <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:input type="number" step="0.01" min="0" wire:model="prof_base_salary" :label="__('Fixed Monthly Salary (₦)')" />
                    <flux:input type="number" step="0.01" min="0" wire:model="prof_custom_rate" :label="__('Custom Contact/Unit Rate (₦)')" :placeholder="__('Leave blank to inherit')" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <flux:input wire:model="prof_bank_name" :label="__('Bank Name')" />
                <flux:input wire:model="prof_account_number" :label="__('Account No.')" />
                <flux:input wire:model="prof_account_name" :label="__('Account Name')" />
            </div>

            <flux:select wire:model="prof_status" :label="__('Payroll Status')">
                <flux:select.option value="active">{{ __('Active (Included in Runs)') }}</flux:select.option>
                <flux:select.option value="suspended">{{ __('Suspended (Excluded)') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showProfileModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Profile') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
