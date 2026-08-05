<?php

namespace App\Enums;

enum VivaProcessingStatus: string
{
    case NotStarted = 'not_started';
    case MappingImported = 'mapping_imported';
    case BoardDataImported = 'board_data_imported';
    case ReconciliationRunning = 'reconciliation_running';
    case ReconciliationGenerated = 'reconciliation_generated';
    case ProcessingReady = 'processing_ready';
    case ProcessingRunning = 'processing_running';
    case ProcessingCompleted = 'processing_completed';
    case ResultFinalizing = 'result_finalizing';
    case ResultFinalized = 'result_finalized';
    case Reopened = 'reopened';
}
