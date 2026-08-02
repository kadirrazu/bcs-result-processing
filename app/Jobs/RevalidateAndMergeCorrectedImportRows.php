<?php

namespace App\Jobs;

use App\Models\Examination;
use Illuminate\Support\Facades\DB;
use App\Services\Imports\CorrectedRowMergeService;
use App\Services\Preliminary\PreliminaryValidationService;
use App\Services\Registrations\RegistrationStagingValidationService;
use App\Services\Written\WrittenValidationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Revalidates an approved source batch, then merges only the corrected source rows. */
final class RevalidateAndMergeCorrectedImportRows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    /** @param list<int> $sourceRows */
    public function __construct(
        public readonly int $examinationId,
        public readonly string $module,
        public readonly int $batchId,
        public readonly array $sourceRows,
        public readonly int $actorId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(
        ExaminationConnectionManager $connections,
        RegistrationStagingValidationService $registrationValidation,
        PreliminaryValidationService $preliminaryValidation,
        WrittenValidationService $writtenValidation,
        CorrectedRowMergeService $merge,
    ): void {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);

        try {
            match ($this->module) {
                'registration' => $registrationValidation->validate($this->batchId),
                'preliminary' => $preliminaryValidation->validate($this->batchId),
                'written' => $writtenValidation->validate($this->batchId, true),
                default => throw new \RuntimeException('Unsupported import correction module.'),
            };

            $merge->merge($this->module, $this->batchId, $this->sourceRows, $this->actorId);
        } catch (\Throwable $exception) {
            $table = match ($this->module) {
                'registration' => 'registration_import_batches',
                'preliminary' => 'preliminary_import_batches',
                'written' => 'written_import_batches',
                default => null,
            };
            if ($table !== null) {
                DB::connection('exam')->table($table)->where('id', $this->batchId)->update([
                    'status' => 'failed',
                    'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
            throw $exception;
        } finally {
            $connections->disconnect();
        }
    }
}
