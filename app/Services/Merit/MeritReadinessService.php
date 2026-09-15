<?php

namespace App\Services\Merit;

use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\Tabulation\TabulationFinalizedDatasetService;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MeritReadinessService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly TabulationFinalizedDatasetService $tabulation,
    ) {}

    /** @return array<string,mixed> */
    public function inspect(): array { return $this->buildInspection(false); }

    /** @return array<string,mixed> */
    public function assertReady(): array
    {
        $inspection = $this->buildInspection(true);
        if (! $inspection['ready']) {
            $reasons = collect($inspection['checks'])->reject(fn (array $check): bool => $check['ready'])->map(fn (array $check): string => $check['label'].': '.$check['detail'])->implode(' | ');
            throw ValidationException::withMessages(['merit' => 'Merit Generation readiness failed. '.$reasons]);
        }
        return $inspection;
    }

    /** @return array<string,mixed> */
    private function buildInspection(bool $verifyHashes): array
    {
        $checks=[];$snapshot=[];
        try {
            $circular=$verifyHashes?$this->circular->verifiedSummary():$this->circular->storedFinalizedSummary();
            $checks['circular']=['ready'=>true,'label'=>'Circular','detail'=>$verifyHashes?'Finalized dataset hash verified.':'Finalized dataset ready. Stored hash will be re-verified before processing.'];
            $snapshot['circular']=['version'=>(int)$circular['version'],'dataset_hash'=>(string)$circular['dataset_hash']];
        } catch(Throwable $e){$checks['circular']=['ready'=>false,'label'=>'Circular','detail'=>$this->message($e)];}
        try {
            $tabulation=$verifyHashes?$this->tabulation->verifiedSummary():$this->tabulation->storedFinalizedSummary();
            $checks['tabulation']=['ready'=>true,'label'=>'Tabulation','detail'=>$verifyHashes?'Finalized dataset hash verified.':'Finalized dataset ready. Stored hash will be re-verified before processing.'];
            $snapshot['tabulation']=['processing_run_id'=>(int)$tabulation['processing_run_id'],'processing_version'=>(int)$tabulation['processing_version'],'dataset_hash'=>(string)$tabulation['dataset_hash']];
        } catch(Throwable $e){$checks['tabulation']=['ready'=>false,'label'=>'Tabulation','detail'=>$this->message($e)];}
        return ['ready'=>collect($checks)->every(fn(array $check):bool=>$check['ready']),'checks'=>$checks,'source_snapshot'=>$snapshot,'hash_verification_mode'=>$verifyHashes?'STRICT_LIVE_HASH_VERIFICATION':'STORED_FINALIZED_HASH_STATUS'];
    }

    private function message(Throwable $e):string
    {
        if($e instanceof ValidationException)return collect($e->errors())->flatten()->first()??$e->getMessage();
        return $e->getMessage();
    }
}
