<?php

namespace App\Enums;

enum PlacementWorkflowStage: string
{
    case PENDING_SELECTION = 'Pending_Selection';
    case PENDING_REQUEST_APPROVAL = 'Pending_Request_Approval';
    case REQUEST_APPROVED = 'Request_Approved';
    case ACCEPTANCE_SUBMITTED = 'Acceptance_Submitted';
    case POSTING_ISSUED = 'Posting_Issued';

    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_SELECTION => 'red',
            self::PENDING_REQUEST_APPROVAL => 'yellow',
            self::REQUEST_APPROVED => 'blue',
            self::ACCEPTANCE_SUBMITTED => 'purple',
            self::POSTING_ISSUED => 'green',
        };
    }
}
