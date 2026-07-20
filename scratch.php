<?php
$content = file_get_contents('resources/views/livewire/admin/placements/manage.blade.php');

// 1. Add properties for create modal
$propertiesOld = <<<OLD
    // Form fields for new placement
    public \$student_id = '';
OLD;

$propertiesNew = <<<NEW
    // Form fields for new placement
    public \$create_program_id = '';
    public \$create_level = '';
    public \$student_id = '';
NEW;

$content = str_replace($propertiesOld, $propertiesNew, $content);

// 2. Reset the new fields
$resetOld = <<<OLD
    public function createPlacement()
    {
        \$this->reset([
            'student_id', 'organization_id', 'placement_type_id', 'start_date', 'end_date', 'academic_session',
OLD;

$resetNew = <<<NEW
    public function createPlacement()
    {
        \$this->reset([
            'create_program_id', 'create_level', 'student_id', 'organization_id', 'placement_type_id', 'start_date', 'end_date', 'academic_session',
NEW;

$content = str_replace($resetOld, $resetNew, $content);

// 3. Update with() method
$withOld = <<<OLD
            'students' => Student::select('id', 'first_name', 'last_name', 'matric_number')->take(100)->get(),
            'organizations' => Organization::where('active_status', true)->get(),
            'types' => PlacementType::where('is_active', true)->get(),
            'templates' => DocumentTemplate::where('active', true)->get(),
            'programs' => Program::orderBy('name')->get(),
            'academicSessions' => AcademicSession::orderBy('name', 'desc')->get(),
        ];
OLD;

$withNew = <<<NEW
            'modalStudents' => (\$this->create_program_id && \$this->create_level && \$activeSession)
                ? Student::where('program_id', \$this->create_program_id)
                    ->whereRaw("entry_level + (CAST(SUBSTRING_INDEX(?, '/', 1) AS SIGNED) - CAST(admission_year AS SIGNED)) * 100 = ?", [
                        \$activeSession->name,
                        \$this->create_level,
                    ])->orderBy('first_name')->get()
                : collect(),
            'organizations' => Organization::where('active_status', true)->get(),
            'types' => PlacementType::where('is_active', true)->get(),
            'templates' => DocumentTemplate::where('active', true)->get(),
            'programs' => Program::when(auth()->user()->institution_id, fn(\$q) => \$q->where('institution_id', auth()->user()->institution_id))->orderBy('name')->get(),
            'academicSessions' => AcademicSession::orderBy('name', 'desc')->get(),
        ];
NEW;

$content = str_replace($withOld, $withNew, $content);

// 4. Update the Modal UI in blade
$uiOld = <<<OLD
            <form wire:submit.prevent="savePlacement">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <flux:select wire:model="student_id" label="Student" placeholder="Select Student" required>
                            @foreach(\$students as \$student)
                                <option value="{{ \$student->id }}">{{ \$student->full_name }} ({{ \$student->matric_number }})</option>
                            @endforeach
                        </flux:select>
                    </div>
OLD;

$uiNew = <<<NEW
            <form wire:submit.prevent="savePlacement">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:select wire:model.live="create_program_id" label="Program">
                        <option value="">Filter by Program...</option>
                        @foreach(\$programs as \$program)
                            <option value="{{ \$program->id }}">{{ \$program->name }}</option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select wire:model.live="create_level" label="Level">
                        <option value="">Filter by Level...</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                        <option value="500">500 Level</option>
                        <option value="600">600 Level</option>
                    </flux:select>

                    <div class="md:col-span-2">
                        <flux:select wire:model="student_id" label="Student" required :disabled="empty(\$modalStudents)">
                            <option value="">{{ empty(\$modalStudents) ? 'Select Program and Level first...' : 'Select Student' }}</option>
                            @foreach(\$modalStudents as \$student)
                                <option value="{{ \$student->id }}">{{ \$student->full_name }} ({{ \$student->matric_number }})</option>
                            @endforeach
                        </flux:select>
                    </div>
NEW;

$content = str_replace($uiOld, $uiNew, $content);

file_put_contents('resources/views/livewire/admin/placements/manage.blade.php', $content);
?>
