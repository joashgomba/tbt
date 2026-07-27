<?php

namespace App\Enums;

enum ComplaintStatusCode: string
{
    case Submitted = 'submitted';
    case UnderValidation = 'under_validation';
    case Rejected = 'rejected';
    case AdditionalInfoRequested = 'additional_info_requested';
    case Assigned = 'assigned';
    case InProcessing = 'in_processing';
    case AwaitingExternalInput = 'awaiting_external_input';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Archived = 'archived';
}
