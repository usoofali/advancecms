<?php

namespace App\Enums;

enum PlacementApprovalStatus: string
{
    case DRAFT = 'Draft';
    case DEPARTMENT_APPROVED = 'Department_Approved';
    case ACADEMIC_APPROVED = 'Academic_Approved';
    case GENERATED = 'Generated';
}
