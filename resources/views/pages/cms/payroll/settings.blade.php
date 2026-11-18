<?php

use App\Models\Department;
use App\Models\Institution;
use App\Models\PayrollCategory;
use App\Models\PayrollComponent;
use App\Models\Staff;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Payroll Categories & Rules')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'categories';

    #[Url]
    public ?int $institution_id = null;

    // Category Modal State
    public bool $showCategoryModal = false;

    public ?int $editingCategoryId = null;

    public string $cat_name = '';

    public string $cat_pay_type = 'fixed';

    public string $cat_contact_formula = 'contact_x_credit_unit';

    public string $cat_default_rate = '0';

    public bool $cat_is_default = false;

    public string $cat_status = 'active';

    public string $cat_description = '';

    // Component (Allowance / Deduction) Modal State
    public bool $showComponentModal = false;

    public ?int $editingComponentId = null;

    public string $comp_name = '';

    public string $comp_type = 'allowance';

    public string $comp_calc_type = 'fixed';

    public string $comp_amount_or_rate = '0';

    public string $comp_scope = 'all';

    public ?int $comp_scope_id = null;

    public bool $comp_is_active = true;

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

    private function resolveInstitutionId(): ?int
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin')
            ? ($this->institution_id ?: Institution::where('status', 'active')->value('id'))
            : $user->institution_id;
    }

    public function seedDefaultCategories(): void
    {
        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $defaults = [
            [
                'name' => 'Permanent Staff',
                'pay_type' => 'fixed',
                'contact_formula' => null,
                'default_rate' => 0,
                'is_default' => true,
                'description' => 'Full-time staff paid a fixed monthly salary.',
            ],
            [
                'name' => 'Casual / Part-Time Lecturer',
                'pay_type' => 'contact_based',
                'contact_formula' => 'contact_x_credit_unit',
                'default_rate' => 1500,
                'is_default' => false,
                'description' => 'Paid per lecture contact multiplied by course credit unit.',
            ],
        ];

        foreach ($defaults as $data) {
            PayrollCategory::firstOrCreate(
                ['institution_id' => $instId, 'name' => $data['name']],
                array_merge($data, ['institution_id' => $instId, 'status' => 'active'])
            );
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => __('Default staff categories loaded.')]);
    }

    public function openCreateCategoryModal(): void
    {
        $this->reset(['editingCategoryId', 'cat_name', 'cat_description']);
        $this->cat_pay_type = 'fixed';
        $this->cat_contact_formula = 'contact_x_credit_unit';
        $this->cat_default_rate = '0';
        $this->cat_is_default = false;
        $this->cat_status = 'active';
        $this->showCategoryModal = true;
    }

    public function openEditCategoryModal(int $id): void
    {
        $instId = $this->resolveInstitutionId();
        $cat = PayrollCategory::where('institution_id', $instId)->findOrFail($id);

        $this->editingCategoryId = $cat->id;
        $this->cat_name = $cat->name;
        $this->cat_pay_type = $cat->pay_type;
        $this->cat_contact_formula = $cat->contact_formula ?: 'contact_x_credit_unit';
        $this->cat_default_rate = (string) $cat->default_rate;
        $this->cat_is_default = (bool) $cat->is_default;
        $this->cat_status = $cat->status;
        $this->cat_description = (string) ($cat->description ?? '');
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $this->validate([
            'cat_name' => 'required|string|max:120',
            'cat_pay_type' => 'required|in:fixed,contact_based',
            'cat_contact_formula' => 'nullable|in:contact_x_credit_unit,hour_x_credit_unit,per_contact,per_hour',
            'cat_default_rate' => 'required|numeric|min:0',
            'cat_status' => 'required|in:active,inactive',
        ]);

        if ($this->cat_is_default) {
            PayrollCategory::where('institution_id', $instId)->update(['is_default' => false]);
        }

        $payload = [
            'institution_id' => $instId,
            'name' => $this->cat_name,
            'pay_type' => $this->cat_pay_type,
            'contact_formula' => $this->cat_pay_type === 'contact_based' ? $this->cat_contact_formula : null,
            'default_rate' => $this->cat_pay_type === 'contact_based' ? (float) $this->cat_default_rate : 0,
            'is_default' => $this->cat_is_default,
            'status' => $this->cat_status,
            'description' => $this->cat_description ?: null,
        ];

        if ($this->editingCategoryId) {
            PayrollCategory::where('institution_id', $instId)->findOrFail($this->editingCategoryId)->update($payload);
        } else {
            PayrollCategory::create($payload);
        }

        $this->showCategoryModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Category saved successfully.')]);
    }

    public function deleteCategory(int $id): void
    {
        $instId = $this->resolveInstitutionId();
        PayrollCategory::where('institution_id', $instId)->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Category deleted.')]);
    }

    public function openCreateComponentModal(): void
    {
        $this->reset(['editingComponentId', 'comp_name', 'comp_scope_id']);
        $this->comp_type = 'allowance';
        $this->comp_calc_type = 'fixed';
        $this->comp_amount_or_rate = '0';
        $this->comp_scope = 'all';
        $this->comp_is_active = true;
        $this->showComponentModal = true;
    }

    public function openEditComponentModal(int $id): void
    {
        $instId = $this->resolveInstitutionId();
        $comp = PayrollComponent::where('institution_id', $instId)->findOrFail($id);

        $this->editingComponentId = $comp->id;
        $this->comp_name = $comp->name;
        $this->comp_type = $comp->type;
        $this->comp_calc_type = $comp->calculation_type;
        $this->comp_amount_or_rate = (string) $comp->amount_or_rate;
        $this->comp_is_active = (bool) $comp->is_active;

        if (! $comp->applicable_type) {
            $this->comp_scope = 'all';
            $this->comp_scope_id = null;
        } elseif ($comp->applicable_type === PayrollCategory::class) {
            $this->comp_scope = 'category';
            $this->comp_scope_id = $comp->applicable_id;
        } elseif ($comp->applicable_type === Department::class) {
            $this->comp_scope = 'department';
            $this->comp_scope_id = $comp->applicable_id;
        } elseif ($comp->applicable_type === Staff::class) {
            $this->comp_scope = 'staff';
            $this->comp_scope_id = $comp->applicable_id;
        }

        $this->showComponentModal = true;
    }

    public function saveComponent(): void
    {
        $instId = $this->resolveInstitutionId();
        if (! $instId) {
            return;
        }

        $this->validate([
            'comp_name' => 'required|string|max:120',
            'comp_type' => 'required|in:allowance,deduction',
            'comp_calc_type' => 'required|in:fixed,percentage',
            'comp_amount_or_rate' => 'required|numeric|min:0',
            'comp_scope' => 'required|in:all,category,department,staff',
            'comp_scope_id' => 'nullable|integer',
        ]);

        $applicableType = match ($this->comp_scope) {
            'category' => PayrollCategory::class,
            'department' => Department::class,
            'staff' => Staff::class,
            default => null,
        };

        $payload = [
            'institution_id' => $instId,
            'name' => $this->comp_name,
            'type' => $this->comp_type,
            'calculation_type' => $this->comp_calc_type,
            'amount_or_rate' => (float) $this->comp_amount_or_rate,
            'applicable_type' => $applicableType,
            'applicable_id' => $applicableType ? $this->comp_scope_id : null,
            'is_active' => $this->comp_is_active,
        ];

        if ($this->editingComponentId) {
            PayrollComponent::where('institution_id', $instId)->findOrFail($this->editingComponentId)->update($payload);
        } else {
            PayrollComponent::create($payload);
        }

        $this->showComponentModal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Rule saved successfully.')]);
    }

    public function deleteComponent(int $id): void
    {
        $instId = $this->resolveInstitutionId();
        PayrollComponent::where('institution_id', $instId)->findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('Rule deleted.')]);
    }

    public function with(): array
    {
        $user = auth()->user();
        $instId = $this->resolveInstitutionId();

        return [
            'institutions' => $user->hasRole('Super Admin') ? Institution::orderBy('name')->get() : collect(),
            'categories' => $instId
                ? PayrollCategory::withCount('staffProfiles')->where('institution_id', $instId)->orderByDesc('is_default')->orderBy('name')->get()
                : collect(),
            'components' => $instId
                ? PayrollComponent::with('applicable')->where('institution_id', $instId)->orderBy('type')->orderBy('name')->paginate(15, ['*'], 'componentsPage')
                : new LengthAwarePaginator([], 0, 15, 1, ['pageName' => 'componentsPage']),
            'departments' => $instId ? Department::where('institution_id', $instId)->orderBy('name')->get() : collect(),
            'staffList' => $instId ? Staff::where('institution_id', $instId)->where('status', 'active')->orderBy('first_name')->get() : collect(),
            'formulas' => PayrollCategory::contactFormulaLabels(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Categories & Rules') }}</flux:heading>
            <flux:subheading>{{ __('Set up staff payment categories (fixed or per-contact/credit unit) and allowances or deductions') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()->hasRole('Super Admin'))
                <flux:select wire:model.live="institution_id" class="w-full sm:w-56">
                    @foreach($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if($tab === 'categories')
                <flux:button variant="primary" icon="plus" wire:click="openCreateCategoryModal" class="w-full sm:w-auto">
                    {{ __('Add Category') }}
                </flux:button>
            @else
                <flux:button variant="primary" icon="plus" wire:click="openCreateComponentModal" class="w-full sm:w-auto">
                    {{ __('Add Allowance / Deduction') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto">
        <button type="button" wire:click="$set('tab', 'categories')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'categories' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.tag class="size-4" />
            {{ __('Staff Categories') }}
            <flux:badge size="sm" color="zinc">{{ $categories->count() }}</flux:badge>
        </button>

        <button type="button" wire:click="$set('tab', 'components')"
            class="pb-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 {{ $tab === 'components' ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400' }}">
            <flux:icon.adjustments-horizontal class="size-4" />
            {{ __('Allowances & Deductions') }}
            <flux:badge size="sm" color="zinc">{{ $components->count() }}</flux:badge>
        </button>
    </div>

    @if($tab === 'categories')
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-900/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div>
                <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ __('Institution Staff Categories') }}</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Define default payment methods (e.g., Permanent Fixed vs Casual Contact/Credit Unit). Individual staff can still override these anytime.') }}
                </p>
            </div>
            <flux:button size="sm" variant="filled" icon="sparkles" wire:click="seedDefaultCategories" class="shrink-0">
                {{ __('Load Default Categories') }}
            </flux:button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($categories as $cat)
                <div wire:key="pay-cat-{{ $cat->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-5 shadow-xs flex flex-col justify-between gap-4">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $cat->name }}</h4>
                                @if($cat->is_default)
                                    <flux:badge size="sm" color="indigo">{{ __('Institution Default') }}</flux:badge>
                                @endif
                            </div>
                            <flux:badge size="sm" :color="$cat->pay_type === 'fixed' ? 'emerald' : 'amber'">
                                {{ $cat->pay_type === 'fixed' ? __('Fixed Salary') : __('Per Contact / Unit') }}
                            </flux:badge>
                        </div>

                        @if($cat->pay_type === 'contact_based')
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-900/60 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-700">
                                <div><strong>{{ __('Formula:') }}</strong> {{ $cat->contact_formula_label }}</div>
                                <div class="mt-0.5"><strong>{{ __('Default Rate:') }}</strong> ₦{{ number_format((float) $cat->default_rate, 2) }}</div>
                            </div>
                        @endif

                        @if($cat->description)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $cat->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                        <span class="text-zinc-500">{{ $cat->staff_profiles_count }} {{ Str::plural('staff assigned', $cat->staff_profiles_count) }}</span>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditCategoryModal({{ $cat->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteCategory({{ $cat->id }})" wire:confirm="{{ __('Delete this category?') }}" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center text-zinc-500">
                    <p class="mb-3">{{ __('No payroll categories configured yet.') }}</p>
                    <flux:button size="sm" variant="primary" wire:click="seedDefaultCategories">{{ __('Load Default Categories') }}</flux:button>
                </div>
            @endforelse
        </div>
    @else
        {{-- Desktop Table --}}
        <div class="hidden md:block">
            <flux:card>
                <div class="relative overflow-x-auto">
                    <flux:table :paginate="$components">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Amount / Rate') }}</flux:table.column>
                            <flux:table.column>{{ __('Applies To') }}</flux:table.column>
                            <flux:table.column class="text-center">{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($components as $comp)
                                <flux:table.row :key="$comp->id">
                                    <flux:table.cell class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $comp->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$comp->type === 'allowance' ? 'green' : 'red'">
                                            {{ ucfirst($comp->type) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="font-mono text-xs">
                                        @if($comp->calculation_type === 'percentage')
                                            {{ number_format((float) $comp->amount_or_rate, 2) }}% {{ __('of Base Pay') }}
                                        @else
                                            ₦{{ number_format((float) $comp->amount_or_rate, 2) }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">{{ $comp->scope_label }}</flux:table.cell>
                                    <flux:table.cell class="text-center">
                                        <flux:badge size="sm" :color="$comp->is_active ? 'green' : 'zinc'">
                                            {{ $comp->is_active ? __('Active') : __('Inactive') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditComponentModal({{ $comp->id }})">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="deleteComponent({{ $comp->id }})" wire:confirm="{{ __('Delete this rule?') }}">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">{{ __('No allowances or deductions defined yet.') }}</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:card>
        </div>

        {{-- Mobile Cards --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($components as $comp)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $comp->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $comp->scope_label }}</div>
                        </div>
                        <flux:badge size="sm" :color="$comp->type === 'allowance' ? 'green' : 'red'">{{ ucfirst($comp->type) }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                        <span class="font-mono font-bold">
                            {{ $comp->calculation_type === 'percentage' ? number_format((float) $comp->amount_or_rate, 2).'%' : '₦'.number_format((float) $comp->amount_or_rate, 2) }}
                        </span>
                        <div class="flex items-center gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openEditComponentModal({{ $comp->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteComponent({{ $comp->id }})" wire:confirm="{{ __('Delete this rule?') }}" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center text-sm text-zinc-500">
                    {{ __('No allowances or deductions defined yet.') }}
                </div>
            @endforelse
            <div>{{ $components->links() }}</div>
        </div>
    @endif

    {{-- Category Modal --}}
    <flux:modal wire:model.self="showCategoryModal" class="max-w-lg w-full">
        <form wire:submit="saveCategory" class="space-y-4">
            <flux:heading size="lg">{{ $editingCategoryId ? __('Edit Staff Category') : __('New Staff Category') }}</flux:heading>

            <flux:input wire:model="cat_name" :label="__('Category Name')" :placeholder="__('e.g. Permanent Staff, Casual Lecturer')" required />

            <flux:select wire:model.live="cat_pay_type" :label="__('Payment Method')" required>
                <flux:select.option value="fixed">{{ __('Fixed Monthly Salary') }}</flux:select.option>
                <flux:select.option value="contact_based">{{ __('Per Contact / Hour / Credit Unit') }}</flux:select.option>
            </flux:select>

            @if($cat_pay_type === 'contact_based')
                <flux:select wire:model="cat_contact_formula" :label="__('Calculation Formula')" required>
                    @foreach($formulas as $key => $label)
                        <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" step="0.01" min="0" wire:model="cat_default_rate" :label="__('Default Rate (₦)')" required />
            @endif

            <flux:textarea wire:model="cat_description" :label="__('Description (Optional)')" rows="2" />

            <div class="flex items-center justify-between gap-4">
                <flux:checkbox wire:model="cat_is_default" :label="__('Set as Institution Default Category')" />
                <flux:select wire:model="cat_status" class="w-32">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showCategoryModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Category') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Allowance / Deduction Modal --}}
    <flux:modal wire:model.self="showComponentModal" class="max-w-lg w-full">
        <form wire:submit="saveComponent" class="space-y-4">
            <flux:heading size="lg">{{ $editingComponentId ? __('Edit Rule') : __('New Allowance / Deduction') }}</flux:heading>

            <flux:input wire:model="comp_name" :label="__('Rule Name')" :placeholder="__('e.g. Housing Allowance, PAYE Tax, Staff Welfare')" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:select wire:model="comp_type" :label="__('Type')">
                    <flux:select.option value="allowance">{{ __('Allowance (+)') }}</flux:select.option>
                    <flux:select.option value="deduction">{{ __('Deduction (-)') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="comp_calc_type" :label="__('Calculation')">
                    <flux:select.option value="fixed">{{ __('Fixed Amount (₦)') }}</flux:select.option>
                    <flux:select.option value="percentage">{{ __('Percentage (%) of Base Pay') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:input type="number" step="0.01" min="0" wire:model="comp_amount_or_rate" :label="__('Amount (₦) or Percentage (%)')" required />

            <flux:select wire:model.live="comp_scope" :label="__('Applies To')">
                <flux:select.option value="all">{{ __('All Staff in Institution') }}</flux:select.option>
                <flux:select.option value="category">{{ __('Specific Staff Category') }}</flux:select.option>
                <flux:select.option value="department">{{ __('Specific Department') }}</flux:select.option>
                <flux:select.option value="staff">{{ __('Specific Staff Member') }}</flux:select.option>
            </flux:select>

            @if($comp_scope === 'category')
                <flux:select wire:model="comp_scope_id" :label="__('Select Category')" required>
                    <flux:select.option value="">{{ __('Choose category...') }}</flux:select.option>
                    @foreach($categories as $cat)
                        <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif($comp_scope === 'department')
                <flux:select wire:model="comp_scope_id" :label="__('Select Department')" required>
                    <flux:select.option value="">{{ __('Choose department...') }}</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif($comp_scope === 'staff')
                <flux:select wire:model="comp_scope_id" :label="__('Select Staff Member')" required>
                    <flux:select.option value="">{{ __('Choose staff...') }}</flux:select.option>
                    @foreach($staffList as $st)
                        <flux:select.option :value="$st->id">{{ $st->first_name }} {{ $st->last_name }} ({{ $st->staff_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:checkbox wire:model="comp_is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" type="button" wire:click="$set('showComponentModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Rule') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
