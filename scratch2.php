<?php
$content = file_get_contents('scratch_manage.blade.php');

$tableHeaders = <<<OLD
            <flux:table :paginate="\$placements">
                <flux:table.columns>
                    <flux:table.column>Student</flux:table.column>
OLD;

$tableHeadersNew = <<<NEW
            @if(count(\$selectedPlacements) > 0)
                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-between border border-blue-200 dark:border-blue-800">
                    <span class="text-sm text-blue-800 dark:text-blue-300">
                        <strong>{{ count(\$selectedPlacements) }}</strong> placement(s) selected
                    </span>
                    <div class="flex gap-2">
                        <flux:button wire:click="bulkApproveRequests" size="sm" variant="primary" icon="check">
                            Approve Requests
                        </flux:button>
                        <flux:button wire:click="\$set('selectedPlacements', [])" size="sm" variant="ghost">
                            Clear Selection
                        </flux:button>
                    </div>
                </div>
            @endif

            <flux:table :paginate="\$placements">
                <flux:table.columns>
                    <flux:table.column>
                        <flux:checkbox wire:model.live="selectAll" />
                    </flux:table.column>
                    <flux:table.column>Student</flux:table.column>
NEW;

$content = str_replace($tableHeaders, $tableHeadersNew, $content);

$tableRow = <<<OLD
                        <flux:table.row>
                            <flux:table.cell>
OLD;

$tableRowNew = <<<NEW
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:checkbox wire:model="selectedPlacements" value="{{ \$placement->id }}" />
                            </flux:table.cell>
                            <flux:table.cell>
NEW;

$content = str_replace($tableRow, $tableRowNew, $content);

$badgeOld = <<<OLD
                                @php
                                    \$stageColors = [
                                        'Pending_Selection' => 'red',
                                        'Pending_Request_Approval' => 'yellow',
                                        'Request_Approved' => 'blue',
                                        'Acceptance_Submitted' => 'purple',
                                        'Posting_Issued' => 'green',
                                    ];
                                    \$stageColor = \$stageColors[\$placement->workflow_stage] ?? 'gray';
                                @endphp
                                <flux:badge color="{{ \$stageColor }}" size="sm">
                                    {{ str_replace('_', ' ', \$placement->workflow_stage ?: 'Assigned') }}
                                </flux:badge>
OLD;

$badgeNew = <<<NEW
                                @php
                                    \$stageEnum = \App\Enums\PlacementWorkflowStage::tryFrom(\$placement->workflow_stage);
                                    \$stageColor = \$stageEnum ? \$stageEnum->color() : 'gray';
                                    \$stageLabel = \$stageEnum ? \$stageEnum->label() : (\$placement->workflow_stage ?: 'Assigned');
                                @endphp
                                <flux:badge color="{{ \$stageColor }}" size="sm">
                                    {{ \$stageLabel }}
                                </flux:badge>
NEW;

$content = str_replace($badgeOld, $badgeNew, $content);

file_put_contents('scratch_manage.blade.php', $content);
?>
