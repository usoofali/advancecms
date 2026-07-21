<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PlacementType;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $deleteModal = false;

    // Form fields
    public ?int $typeId = null;
    public string $name = '';
    public string $description = '';
    public bool $is_active = true;

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function resetForm()
    {
        $this->reset(['typeId', 'name', 'description', 'is_active']);
        $this->resetValidation();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(PlacementType $type)
    {
        $this->resetForm();
        $this->typeId = $type->id;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->is_active = $type->is_active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->typeId) {
            PlacementType::find($this->typeId)->update($validated);
            Flux::toast('Placement Type updated successfully.', variant: 'success');
        } else {
            PlacementType::create($validated);
            Flux::toast('Placement Type created successfully.', variant: 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($id)
    {
        $this->typeId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        if ($this->typeId) {
            PlacementType::find($this->typeId)?->delete();
            Flux::toast('Placement Type deleted successfully.', variant: 'danger');
        }
        $this->deleteModal = false;
        $this->typeId = null;
    }

    public function toggleStatus(PlacementType $type)
    {
        $type->update(['is_active' => !$type->is_active]);
        Flux::toast('Status updated.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'types' => PlacementType::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
                ->orderBy('name')
                ->paginate(10)
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Placement Types</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage categories of student placements.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search types..." class="w-full sm:w-64" />
            <flux:button wire:click="create" variant="primary" icon="plus" class="w-full sm:w-auto shrink-0">Add Type</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$types">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>
                
                <flux:table.rows>
                    @forelse($types as $type)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $type->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="text-sm text-gray-500">{{ Str::limit($type->description, 50) ?? 'N/A' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <button wire:click="toggleStatus({{ $type->id }})" class="focus:outline-none">
                                    <flux:badge color="{{ $type->is_active ? 'green' : 'gray' }}" size="sm" class="cursor-pointer">
                                        {{ $type->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </button>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="edit({{ $type->id }})" icon="pencil-square">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $type->id }})" icon="trash" variant="danger">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-8 text-gray-500">
                                No placement types found.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showModal" class="sm:w-full sm:max-w-md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                {{ $typeId ? 'Edit Placement Type' : 'Add New Placement Type' }}
            </h2>
            
            <form wire:submit.prevent="save" class="space-y-4">
                <flux:input wire:model="name" label="Type Name" placeholder="e.g., Industrial Training" required />
                
                <flux:textarea wire:model="description" label="Description" rows="3" />
                
                <flux:switch wire:model="is_active" label="Active Status" />
                
                <div class="mt-6 flex justify-end space-x-3">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save Type</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="deleteModal" class="w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center space-x-3 text-red-600 mb-4">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
                <h2 class="text-lg font-medium">Delete Placement Type?</h2>
            </div>
            <p class="text-gray-500 mb-6">Are you sure you want to delete this type? This action cannot be undone and may affect existing placements.</p>
            
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                <flux:button wire:click="$set('deleteModal', false)" variant="ghost" class="w-full sm:w-auto order-2 sm:order-1">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger" class="w-full sm:w-auto order-1 sm:order-2">Yes, Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
