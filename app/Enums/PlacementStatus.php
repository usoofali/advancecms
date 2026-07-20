<?php

namespace App\Enums;

enum PlacementStatus: string
{
    case PENDING = 'Pending';
    case ASSIGNED = 'Assigned';
    case ACCEPTED = 'Accepted';
    case CANCELLED = 'Cancelled';
    case COMPLETED = 'Completed';
}
