<?php

namespace App\Enums;

enum PreliminaryProcessingStatus: string
{
    case NotStarted = 'not_started';
    case MarkImported = 'mark_imported';
    case ReconciliationGenerated = 'reconciliation_generated';
    case CutoffSet = 'cutoff_set';
    case ResultFinalized = 'result_finalized';
    case Reopened = 'reopened';
}
