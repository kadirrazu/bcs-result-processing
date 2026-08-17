<?php

namespace App\Jobs;

use App\Models\ChoiceValidationRun;
use App\Models\Examination;
use App\Services\ChoiceValidation\ChoiceValidationProcessingService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessChoiceValidation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $runId,
    ) {
        $this->onQueue((string) config('choice-validation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        ChoiceValidationProcessingService $service,
    ): void {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $service->run($this->runId);
        } catch (Throwable $e) {
            // Keep the original exception visible even when a database exception
            // contains a very large SQL statement. The run table is operational
            // metadata only; never let failure-message persistence mask the real error.
            Log::error('Choice Validation processing failed.', [
                'examination_id' => $this->examinationId,
                'run_id' => $this->runId,
                'exception' => $e,
            ]);

            ChoiceValidationRun::query()
                ->whereKey($this->runId)
                ->update([
                    'status' => 'failed',
                    'failure_message' => mb_substr($e->getMessage(), 0, 8000),
                    'finished_at' => now(),
                ]);

            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
