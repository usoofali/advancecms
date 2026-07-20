<?php
$content = file_get_contents('scratch_manage.blade.php');

// Add selectAll property and method
$selectAllOld = <<<OLD
    public \$selectedPlacements = [];

    public function updatingSearch()
OLD;

$selectAllNew = <<<NEW
    public \$selectedPlacements = [];
    public \$selectAll = false;

    public function updatedSelectAll(\$value)
    {
        if (\$value) {
            \$this->selectedPlacements = \$this->with()['placements']->pluck('id')->map(fn(\$id) => (string)\$id)->toArray();
        } else {
            \$this->selectedPlacements = [];
        }
    }

    public function updatingSearch()
NEW;

$content = str_replace($selectAllOld, $selectAllNew, $content);

file_put_contents('scratch_manage.blade.php', $content);
?>
