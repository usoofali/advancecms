<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\DocumentTemplate;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $deleteModal = false;

    // Form fields
    public ?int $templateId = null;
    public string $title = '';
    public string $type = '';
    public string $template_content = '';
    public bool $active = true;

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'template_content' => 'required|string',
            'active' => 'boolean',
        ];
    }

    public function resetForm()
    {
        $this->reset(['templateId', 'title', 'type', 'template_content', 'active']);
        $this->resetValidation();
    }

    public function create()
    {
        $this->resetForm();
        
        // Give a default template
        $this->template_content = "<h2>{organization_name}</h2>\n<p>{organization_address}</p>\n<p>Dear Sir/Madam,</p>\n<h3>LETTER OF INTRODUCTION: {student_name} ({matric_number})</h3>\n<p>This is to introduce the above named student of {department} department.</p>";
        
        $this->showModal = true;
    }

    public function edit(DocumentTemplate $template)
    {
        $this->resetForm();
        $this->templateId = $template->id;
        $this->title = $template->title;
        $this->type = $template->type ?? '';
        $this->template_content = $template->template_content;
        $this->active = $template->active;
        
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->templateId) {
            DocumentTemplate::find($this->templateId)->update($validated);
            Flux::toast('Template updated successfully.', variant: 'success');
        } else {
            DocumentTemplate::create($validated);
            Flux::toast('Template created successfully.', variant: 'success');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete($id)
    {
        $this->templateId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        if ($this->templateId) {
            DocumentTemplate::find($this->templateId)?->delete();
            Flux::toast('Template deleted successfully.', variant: 'danger');
        }
        $this->deleteModal = false;
        $this->templateId = null;
    }

    public function toggleStatus(DocumentTemplate $template)
    {
        $template->update(['active' => !$template->active]);
        Flux::toast('Status updated.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'templates' => DocumentTemplate::query()
                ->when($this->search, fn ($query) => $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('type', 'like', '%' . $this->search . '%')
                )
                ->orderBy('title')
                ->paginate(10)
        ];
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Document Templates</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create HTML templates for PDF generation with dynamic placeholders.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search templates..." class="w-full sm:w-64" />
            <flux:button wire:click="create" variant="primary" icon="plus" class="w-full sm:w-auto shrink-0">Add Template</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$templates">
                <flux:table.columns>
                    <flux:table.column>Title</flux:table.column>
                    <flux:table.column>Type / Category</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>
                
                <flux:table.rows>
                    @forelse($templates as $template)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $template->title }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge color="gray" size="sm">{{ $template->type ?? 'General' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <button wire:click="toggleStatus({{ $template->id }})" class="focus:outline-none">
                                    <flux:badge color="{{ $template->active ? 'green' : 'gray' }}" size="sm" class="cursor-pointer">
                                        {{ $template->active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </button>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="edit({{ $template->id }})" icon="pencil-square">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $template->id }})" icon="trash" variant="danger">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-8 text-gray-500">
                                No templates found.
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
                {{ $templateId ? 'Edit Document Template' : 'Add New Template' }}
            </h2>
            
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="title" label="Template Title" placeholder="e.g., Standard IT Letter" required />
                    
                    <flux:input wire:model="type" label="Template Type" placeholder="e.g., Introduction, Acceptance" />
                    
                    <div class="md:col-span-2 space-y-2">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Available Placeholders</div>
                        <div class="flex flex-wrap gap-2 text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-slate-800 p-3 rounded-md">
                            <code>{template_title}</code>, <code>{student_name}</code>, <code>{matric_number}</code>, <code>{department}</code>, 
                            <code>{organization_name}</code>, <code>{organization_address}</code>, <code>{start_date}</code>, 
                            <code>{end_date}</code>, <code>{academic_session}</code>, <code>{document_number}</code>, <code>{qr_code}</code>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <flux:textarea wire:model="template_content" label="HTML Template Content" rows="12" class="font-mono text-sm" required />
                    </div>
                    
                    <div class="md:col-span-2 flex items-center space-x-2 mt-2">
                        <flux:switch wire:model="active" label="Active Status" />
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                    <flux:button wire:click="$set('showModal', false)" variant="ghost" class="w-full sm:w-auto">Cancel</flux:button>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Save Template</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="deleteModal" class="w-full max-w-md">
        <div class="p-6">
            <div class="flex items-center space-x-3 text-red-600 mb-4">
                <flux:icon.exclamation-triangle class="w-6 h-6" />
                <h2 class="text-lg font-medium">Delete Template?</h2>
            </div>
            <p class="text-gray-500 mb-6">Are you sure you want to delete this template? This action cannot be undone.</p>
            
            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 sm:space-x-3">
                <flux:button wire:click="$set('deleteModal', false)" variant="ghost" class="w-full sm:w-auto order-2 sm:order-1">Cancel</flux:button>
                <flux:button wire:click="delete" variant="danger" class="w-full sm:w-auto order-1 sm:order-2">Yes, Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
