<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Organization;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $deleteModal = false;

    // Form fields
    public ?int $orgId = null;
    public string $name = '';
    public string $category = '';
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $country = 'Nigeria';
    public string $contact_person = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public int $capacity = 0;
    public bool $active_status = true;
    public string $notes = '';

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'capacity' => 'required|integer|min:0',
            'active_status' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function resetForm()
    {
        $this->reset([
            'orgId', 'name', 'category', 'address', 'city', 'state', 'country',
            'contact_person', 'phone', 'email', 'website', 'capacity', 'active_status', 'notes'
        ]);
        $this->resetValidation();
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(Organization $org)
    {
        $this->resetForm();
        $this->orgId = $org->id;
        $this->name = $org->name;
        $this->category = $org->category ?? '';
        $this->address = $org->address ?? '';
        $this->city = $org->city ?? '';
        $this->state = $org->state ?? '';
        $this->country = $org->country ?? 'Nigeria';
        $this->contact_person = $org->contact_person ?? '';
        $this->phone = $org->phone ?? '';
        $this->email = $org->email ?? '';
        $this->website = $org->website ?? '';
        $this->capacity = $org->capacity;
        $this->active_status = $org->active_status;
        $this->notes = $org->notes ?? '';
        
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->orgId) {
            Organization::find($this->orgId)->update($validated);
            Flux::toast('Organization updated successfully.', variant: 'success');
        } else {
            Organization::create($validated);
            Flux::toast('Organization created successfully.', variant: 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($id)
    {
        $this->orgId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        if ($this->orgId) {
            Organization::find($this->orgId)?->delete();
            Flux::toast('Organization deleted successfully.', variant: 'danger');
        }
        $this->deleteModal = false;
        $this->orgId = null;
    }

    public function toggleStatus(Organization $org)
    {
        $org->update(['active_status' => !$org->active_status]);
        Flux::toast('Status updated.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'organizations' => Organization::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('city', 'like', '%' . $this->search . '%')
                    ->orWhere('state', 'like', '%' . $this->search . '%')
                )
                ->orderBy('name')
                ->paginate(10)
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Organizations Directory</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage placement organizations, hospitals, and agencies.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search organizations..." class="w-full sm:w-64" />
            <flux:button wire:click="create" variant="primary" icon="plus" class="w-full sm:w-auto shrink-0">Add Organization</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$organizations">
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Category</flux:table.column>
                    <flux:table.column>Location</flux:table.column>
                    <flux:table.column>Capacity</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>
                
                <flux:table.rows>
                    @forelse($organizations as $org)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $org->name }}</div>
                                <div class="text-xs text-gray-500">{{ $org->email }} • {{ $org->phone }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="gray" size="sm">{{ $org->category ?? 'N/A' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $org->city }}{{ $org->city && $org->state ? ',' : '' }} {{ $org->state }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="{{ $org->capacity > 0 ? 'zinc' : 'red' }}" size="sm">{{ $org->capacity }} slots</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <button wire:click="toggleStatus({{ $org->id }})" class="focus:outline-none">
                                    <flux:badge color="{{ $org->active_status ? 'green' : 'gray' }}" size="sm" class="cursor-pointer">
                                        {{ $org->active_status ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </button>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="edit({{ $org->id }})" icon="pencil-square">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $org->id }})" icon="trash" variant="danger">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-8 text-gray-500">
                                No organizations found.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showModal" class="w-full max-w-4xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                {{ $orgId ? 'Edit Organization' : 'Add New Organization' }}
            </h2>
            
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Organization Name" required />
                    
                    <flux:select wire:model="category" label="Category">
                        <option value="">Select Category</option>
                        <option value="Hospital">Hospital</option>
                        <option value="Primary Health Centre">Primary Health Centre</option>
                        <option value="Laboratory">Laboratory</option>
                        <option value="School">School</option>
                        <option value="NGO">NGO</option>
                        <option value="Government Agency">Government Agency</option>
                        <option value="Private Company">Private Company</option>
                        <option value="Logistics Company">Logistics Company</option>
                        <option value="Manufacturing Company">Manufacturing Company</option>
                        <option value="Agricultural Organization">Agricultural Organization</option>
                        <option value="Media Organization">Media Organization</option>
                        <option value="ICT Company">ICT Company</option>
                        <option value="Other">Other</option>
                    </flux:select>
                    
                    <flux:input wire:model="contact_person" label="Contact Person" />
                    <flux:input wire:model="phone" label="Phone Number" />
                    <flux:input wire:model="email" type="email" label="Email Address" />
                    <flux:input wire:model="website" type="url" label="Website URL" placeholder="https://" />
                    
                    <div class="md:col-span-2">
                        <flux:input wire:model="address" label="Street Address" />
                    </div>
                    
                    <flux:input wire:model="city" label="City" />
                    <flux:input wire:model="state" label="State" />
                    <flux:input wire:model="country" label="Country" />
                    <flux:input wire:model="capacity" type="number" min="0" label="Placement Capacity" />
                    
                    <div class="md:col-span-2 flex items-center space-x-2 mt-2">
                        <flux:switch wire:model="active_status" label="Active Status" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <flux:textarea wire:model="notes" label="Additional Notes" rows="3" />
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Save Organization</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="deleteModal" class="w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center space-x-3 text-red-600 mb-4">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
                <h2 class="text-lg font-medium">Delete Organization?</h2>
            </div>
            <p class="text-gray-500 mb-6">Are you sure you want to delete this organization? This action cannot be undone and may affect existing placements.</p>
            
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                <flux:button wire:click="$set('deleteModal', false)" variant="ghost" class="w-full sm:w-auto order-2 sm:order-1">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger" class="w-full sm:w-auto order-1 sm:order-2">Yes, Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
