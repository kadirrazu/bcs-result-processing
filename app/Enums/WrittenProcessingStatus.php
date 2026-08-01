<?php

namespace App\Enums;

enum WrittenProcessingStatus: string
{
    case NotStarted = 'not_started';
    case MarksImported = 'marks_imported';
    case ReconciliationGenerated = 'reconciliation_generated';
    case ProcessingReady = 'processing_ready';
    case ResultFinalized = 'result_finalized';
    case Reopened = 'reopened';
}
