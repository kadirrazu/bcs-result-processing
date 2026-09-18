<?php

namespace App\Services\NonCadre\Circular;

use App\Services\NonCadre\NonCadreReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NonCadreCircularWorkflowService
{
    public function __construct(private readonly NonCadreReadinessService $readiness) {}

    public function approveImport(int $importId, ?int $actorId): int
    {
        $this->readiness->requireReady();
        return DB::connection('exam')->transaction(function () use ($importId, $actorId): int {
            $import = DB::connection('exam')->table('non_cadre_circular_imports')->lockForUpdate()->find($importId);
            if (! $import) abort(404);
            if ((int) $import->invalid_rows > 0 || $import->status !== 'validated') throw ValidationException::withMessages(['import' => 'Resolve all invalid Circular rows before approval.']);
            $next = ((int) DB::connection('exam')->table('non_cadre_circular_versions')->max('version')) + 1;
            $versionId = DB::connection('exam')->table('non_cadre_circular_versions')->insertGetId([
                'version' => $next, 'status' => 'draft', 'source_filename' => $import->source_filename, 'source_hash' => $import->source_hash,
                'valid_rows' => $import->valid_rows, 'invalid_rows' => 0, 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $rows = DB::connection('exam')->table('non_cadre_circular_import_rows')->where('import_id', $importId)->where('validation_status', 'valid')->orderBy('row_number')->get();
            $totalSeats = 0;
            foreach ($rows as $row) {
                $data = json_decode($row->normalized_data, true, 512, JSON_THROW_ON_ERROR);
                $totalSeats += (int) $data['post_count'];
                DB::connection('exam')->table('non_cadre_circular_posts')->insert(array_merge($data, ['circular_version_id' => $versionId, 'created_at' => now(), 'updated_at' => now()]));
            }
            DB::connection('exam')->table('non_cadre_circular_versions')->where('id', $versionId)->update(['total_posts' => count($rows), 'total_seats' => $totalSeats, 'updated_at' => now()]);
            DB::connection('exam')->table('non_cadre_circular_imports')->where('id', $importId)->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now(), 'approved_version_id' => $versionId, 'updated_at' => now()]);
            $this->audit('circular', 'import_approved', 'circular_version', $versionId, null, ['version' => $next, 'posts' => count($rows), 'seats' => $totalSeats], $actorId);
            return $versionId;
        });
    }

    public function finalize(int $versionId, ?int $actorId): void
    {
        $this->readiness->requireReady();
        DB::connection('exam')->transaction(function () use ($versionId, $actorId): void {
            $version = DB::connection('exam')->table('non_cadre_circular_versions')->lockForUpdate()->find($versionId);
            if (! $version) abort(404);
            if ($version->status === 'finalized') return;
            if ($version->status !== 'draft') throw ValidationException::withMessages(['version' => 'Only a draft Non-Cadre Circular version can be finalized.']);
            if ((int) $version->total_posts < 1) throw ValidationException::withMessages(['version' => 'The Circular contains no posts.']);

            $previous = DB::connection('exam')->table('non_cadre_circular_versions')->where('status', 'finalized')->where('id', '<>', $versionId)->get();
            DB::connection('exam')->table('non_cadre_circular_versions')->where('status', 'finalized')->where('id', '<>', $versionId)->update(['status' => 'outdated', 'is_stale' => true, 'stale_reason' => 'Superseded by Non-Cadre Circular version '.$version->version, 'staled_at' => now(), 'updated_at' => now()]);
            DB::connection('exam')->table('non_cadre_circular_versions')->where('id', $versionId)->update(['status' => 'finalized', 'finalized_by' => $actorId, 'finalized_at' => now(), 'is_stale' => false, 'stale_reason' => null, 'staled_at' => null, 'updated_at' => now()]);

            $hadPrevious = $previous->isNotEmpty();
            $stateUpdate = ['status' => 'in_progress', 'circular_status' => 'finalized', 'is_stale' => false, 'stale_reason' => null, 'staled_at' => null, 'updated_at' => now()];
            if ($hadPrevious) {
                $reason = 'Non-Cadre Circular advanced to version '.$version->version.'. Re-run all dependent Non-Cadre stages.';
                $stateUpdate += ['seat_breakup_status' => 'stale', 'choice_status' => 'stale', 'allocation_status' => 'stale', 'reporting_status' => 'stale'];
                DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('is_stale', false)->update(['is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now()]);
                DB::connection('exam')->table('non_cadre_choice_imports')->where('is_stale', false)->update(['is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now()]);
                DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale', false)->update(['is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now()]);
            }
            DB::connection('exam')->table('non_cadre_processing_states')->where('id', 1)->update($stateUpdate);
            $this->audit('circular', 'version_finalized', 'circular_version', $versionId, ['status' => $version->status], ['status' => 'finalized', 'version' => $version->version], $actorId);
        });
    }

    private function audit(string $stage, string $action, string $type, int $id, ?array $before, ?array $after, ?int $actorId): void
    {
        DB::connection('exam')->table('non_cadre_processing_audits')->insert(['stage'=>$stage,'action'=>$action,'entity_type'=>$type,'entity_id'=>$id,'before_payload'=>$before ? json_encode($before) : null,'after_payload'=>$after ? json_encode($after) : null,'actor_id'=>$actorId,'created_at'=>now()]);
    }
}
