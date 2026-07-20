<?php

use App\Models\GeneratedDocument;
use App\Services\DocumentGenerationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Print Official Document')] #[Layout('layouts.guest')] class extends Component {
    public GeneratedDocument $document;
    public string $content = '';

    public function mount(string $doc, DocumentGenerationService $service): void
    {
        \Log::info("Print component mounted for document: " . $doc);

        $this->document = GeneratedDocument::where('document_number', $doc)
            ->with(['placement.student.institution', 'placement.organization', 'template'])
            ->firstOrFail();

        $user = auth()->user();

        // Basic Authorization check
        if ($user->hasRole('Student')) {
            if ($user->student && $user->student->id !== $this->document->placement->student_id) {
                abort(403, 'Unauthorized. You can only view your own placement documents.');
            }
        } else {
            // Check if admin has permission to manage placements
            Gate::authorize('placements.manage');
        }

        // Parse placeholders in the template content
        $this->content = $service->parseContent($this->document);
    }
}; ?>

<x-placement-document.alternative-sheet :document="$document" :content="$content" />