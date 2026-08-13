<?php
namespace App\Services\Merit;
use App\Models\MeritProcessingState;use Throwable;
final class MeritStaleService
{
 public function __construct(private readonly MeritReadinessService $readiness,private readonly MeritSourceSnapshotComparator $snapshots){}
 /**
  * Lightweight stale synchronization for page requests.
  * Stored finalized version/hash metadata is compared here; full live dataset
  * hashes are recomputed by MeritReadinessService::assertReady() before Generate,
  * queued processing and Finalize.
  */
 public function synchronize(?array $inspection=null):MeritProcessingState
 {
  $state=MeritProcessingState::query()->firstOrCreate(['id'=>1],['status'=>'not_started']);if(!$state->source_snapshot)return $state;
  try{$inspection??=$this->readiness->inspect();if(!$inspection['ready'])throw new \RuntimeException('One or more upstream finalized datasets are not ready.');$current=$inspection['source_snapshot'];if(!$this->snapshots->equivalent($state->source_snapshot,$current))$state->update(['is_stale'=>true,'status'=>'stale','stale_reason'=>'MERIT_UPSTREAM_DATASET_CHANGED: Circular, Tabulation or Choice Validation stored finalized hash/version no longer matches this Merit run.']);}
  catch(Throwable $e){$state->update(['is_stale'=>true,'status'=>'stale','stale_reason'=>'MERIT_UPSTREAM_NOT_READY: '.$e->getMessage()]);}
  return $state->refresh();
 }
}
