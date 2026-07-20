<?php

namespace App\Livewire\Cms\IdCards;

use App\Models\IdCardTemplate;
use App\Models\Institution;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Manage ID Card Templates')]
class ManageTemplates extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public ?int $institution_id = null;

    #[Url]
    public string $type = 'student'; // student, staff, both

    // Form state
    public bool $showForm = false;

    public ?int $editingTemplateId = null;

    public ?int $form_institution_id = null;

    public string $form_name = '';

    public string $form_type = 'student';

    public string $form_layout = 'classic';

    public string $form_orientation = 'horizontal';

    public string $form_primary_color = '#2563eb';

    public string $form_secondary_color = '#1e40af';

    public string $form_text_color = '#ffffff';

    public string $form_accent_color = '#f59e0b';

    public string $form_header_text = '';

    public string $form_footer_text = '';

    public string $form_font_family = 'Inter, sans-serif';

    public string $form_font_weight = 'normal';

    public string $form_font_style = 'normal';

    public string $form_text_align = 'left';

    public ?string $form_disclaimer_text = null;

    public bool $form_show_signature_line = true;

    public string $form_back_background_color = '#f8fafc';

    public string $form_back_text_color = '#3f3f46';

    public bool $form_show_qr = true;

    public bool $form_show_barcode = true;

    public bool $form_show_blood_group = true;

    public bool $form_is_active = true;

    public $form_background_image;

    public ?string $existing_background_url = null;

    public function mount(): void
    {
        Gate::authorize('id_cards.manage');

        if (! auth()->user()->hasRole('Super Admin')) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['institution_id', 'type'])) {
            $this->resetPage();
        }
    }

    public function createTemplate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editTemplate(int $id): void
    {
        $this->resetForm();
        $template = IdCardTemplate::findOrFail($id);

        $this->editingTemplateId = $template->id;
        $this->form_institution_id = $template->institution_id;
        $this->form_name = $template->name;
        $this->form_type = $template->type;
        $this->form_layout = $template->layout;
        $this->form_orientation = $template->orientation;
        $this->form_primary_color = $template->primary_color;
        $this->form_secondary_color = $template->secondary_color;
        $this->form_text_color = $template->text_color;
        $this->form_accent_color = $template->accent_color;
        $this->form_header_text = $template->header_text ?? '';
        $this->form_footer_text = $template->footer_text ?? '';

        $this->form_font_family = $template->font_family;
        $this->form_font_weight = $template->font_weight;
        $this->form_font_style = $template->font_style;
        $this->form_text_align = $template->text_align;

        $this->form_disclaimer_text = $template->disclaimer_text;
        $this->form_show_signature_line = $template->show_signature_line;
        $this->form_back_background_color = $template->back_background_color;
        $this->form_back_text_color = $template->back_text_color;

        $this->form_show_qr = $template->show_qr;
        $this->form_show_barcode = $template->show_barcode;
        $this->form_show_blood_group = $template->show_blood_group;
        $this->form_is_active = $template->is_active;
        $this->existing_background_url = $template->background_url;

        $this->showForm = true;
    }

    public function saveTemplate(): void
    {
        $validated = $this->validate([
            'form_institution_id' => ['nullable', 'exists:institutions,id'],
            'form_name' => ['required', 'string', 'max:255'],
            'form_type' => ['required', 'in:student,staff,both'],
            'form_layout' => ['required', 'in:classic,modern_sidebar,minimal'],
            'form_orientation' => ['required', 'in:horizontal,vertical'],
            'form_primary_color' => ['required', 'string', 'max:7'],
            'form_secondary_color' => ['required', 'string', 'max:7'],
            'form_text_color' => ['required', 'string', 'max:7'],
            'form_accent_color' => ['required', 'string', 'max:7'],
            'form_header_text' => ['nullable', 'string', 'max:255'],
            'form_footer_text' => ['nullable', 'string', 'max:255'],
            'form_font_family' => ['required', 'string', 'max:100'],
            'form_font_weight' => ['required', 'string', 'max:50'],
            'form_font_style' => ['required', 'string', 'max:50'],
            'form_text_align' => ['required', 'in:left,center,right'],
            'form_disclaimer_text' => ['nullable', 'string', 'max:1000'],
            'form_show_signature_line' => ['boolean'],
            'form_back_background_color' => ['required', 'string', 'max:7'],
            'form_back_text_color' => ['required', 'string', 'max:7'],
            'form_show_qr' => ['boolean'],
            'form_show_barcode' => ['boolean'],
            'form_show_blood_group' => ['boolean'],
            'form_is_active' => ['boolean'],
            'form_background_image' => ['nullable', 'image', 'max:2048'],
        ]);

        if (! auth()->user()->hasRole('Super Admin')) {
            $validated['form_institution_id'] = auth()->user()->institution_id;
        }

        $data = [
            'institution_id' => $validated['form_institution_id'] === '' ? null : $validated['form_institution_id'],
            'name' => $validated['form_name'],
            'type' => $validated['form_type'],
            'layout' => $validated['form_layout'],
            'orientation' => $validated['form_orientation'],
            'primary_color' => $validated['form_primary_color'],
            'secondary_color' => $validated['form_secondary_color'],
            'text_color' => $validated['form_text_color'],
            'accent_color' => $validated['form_accent_color'],
            'header_text' => $validated['form_header_text'] === '' ? null : $validated['form_header_text'],
            'footer_text' => $validated['form_footer_text'] === '' ? null : $validated['form_footer_text'],
            'font_family' => $validated['form_font_family'],
            'font_weight' => $validated['form_font_weight'],
            'font_style' => $validated['form_font_style'],
            'text_align' => $validated['form_text_align'],
            'disclaimer_text' => $validated['form_disclaimer_text'] === '' ? null : $validated['form_disclaimer_text'],
            'show_signature_line' => $validated['form_show_signature_line'],
            'back_background_color' => $validated['form_back_background_color'],
            'back_text_color' => $validated['form_back_text_color'],
            'show_qr' => $validated['form_show_qr'],
            'show_barcode' => $validated['form_show_barcode'],
            'show_blood_group' => $validated['form_show_blood_group'],
            'is_active' => $validated['form_is_active'],
        ];

        if ($this->form_background_image) {
            $data['background_image_path'] = $this->form_background_image->store('id_cards/templates', 'public');
        }

        if ($this->editingTemplateId) {
            $template = IdCardTemplate::findOrFail($this->editingTemplateId);
            if ($this->form_background_image && $template->background_image_path) {
                Storage::disk('public')->delete($template->background_image_path);
            }
            $template->update($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Template updated successfully.']);
        } else {
            IdCardTemplate::create($data);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Template created successfully.']);
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteTemplate(int $id): void
    {
        $template = IdCardTemplate::findOrFail($id);
        if ($template->background_image_path) {
            Storage::disk('public')->delete($template->background_image_path);
        }
        $template->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Template deleted successfully.']);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingTemplateId', 'form_institution_id', 'form_name', 'form_type',
            'form_layout', 'form_orientation', 'form_primary_color', 'form_secondary_color',
            'form_text_color', 'form_accent_color', 'form_header_text', 'form_footer_text',
            'form_font_family', 'form_font_weight', 'form_font_style', 'form_text_align',
            'form_disclaimer_text', 'form_show_signature_line', 'form_back_background_color', 'form_back_text_color',
            'form_show_qr', 'form_show_barcode', 'form_show_blood_group', 'form_is_active',
            'form_background_image', 'existing_background_url',
        ]);

        $this->form_primary_color = '#2563eb';
        $this->form_secondary_color = '#1e40af';
        $this->form_text_color = '#ffffff';
        $this->form_accent_color = '#f59e0b';
        $this->form_layout = 'classic';
        $this->form_orientation = 'horizontal';
        $this->form_type = 'student';

        $this->form_font_family = 'Inter, sans-serif';
        $this->form_font_weight = 'normal';
        $this->form_font_style = 'normal';
        $this->form_text_align = 'left';

        $this->form_back_background_color = '#f8fafc';
        $this->form_back_text_color = '#3f3f46';
        $this->form_show_signature_line = true;

        $this->form_show_qr = true;
        $this->form_show_barcode = true;
        $this->form_show_blood_group = true;
        $this->form_is_active = true;
    }

    public function with(): array
    {
        $query = IdCardTemplate::query()->with('institution');

        if ($this->institution_id) {
            $query->where('institution_id', $this->institution_id);
        } elseif (! auth()->user()->hasRole('Super Admin')) {
            $query->where('institution_id', auth()->user()->institution_id);
        }

        if ($this->type !== 'both') {
            $query->whereIn('type', [$this->type, 'both']);
        }

        return [
            'templates' => $query->orderBy('name')->paginate(20),
            'institutions' => auth()->user()->hasRole('Super Admin')
                ? Institution::where('status', 'active')->orderBy('name')->get()
                : Institution::where('id', auth()->user()->institution_id)->get(),
        ];
    }
}
