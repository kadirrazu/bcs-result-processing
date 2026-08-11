<?php

namespace App\Services\ChoiceValidation;

use App\Enums\VivaCandidateStatus;
use App\Enums\VivaProcessingStatus;
use App\Enums\VivaResultStatus;
use App\Models\VivaFinalizationRun;
use App\Models\VivaProcessingState;
use App\Models\VivaResult;
use BackedEnum;
use RuntimeException;

final class ChoiceCandidateTrackResolver
{
    private ?int $finalizedProcessingRunId = null;

    public function assertVivaReady(): void
    {
        $state = VivaProcessingState::query()->first();

        if (! $state
            || $state->status !== VivaProcessingStatus::ResultFinalized
            || $state->is_stale
            || ! $state->result_finalized_at) {
            throw new RuntimeException('Finalize the current Viva result before Choice Validation.');
        }

        $finalization = VivaFinalizationRun::query()
            ->where('status', 'current')
            ->latest('id')
            ->first();

        if (! $finalization || ! $finalization->processing_run_id || ! $finalization->finalized_at) {
            throw new RuntimeException('The current finalized Viva processing run could not be resolved.');
        }

        $this->finalizedProcessingRunId = (int) $finalization->processing_run_id;
    }

    /** @return array{eligible:bool,track:?string,written_track:?string,status:string,reason_code:?string,reason_message:?string} */
    public function resolve(int $registrationId): array
    {
        return $this->resolveMany([$registrationId])[$registrationId]
            ?? [
                'eligible' => false,
                'track' => null,
                'written_track' => null,
                'status' => 'not_applicable_due_to_missing_viva_result',
                'reason_code' => 'CANDIDATE_MISSING_VIVA_RESULT',
                'reason_message' => 'No Viva result exists for the candidate in the current finalized Viva processing run.',
            ];
    }

    /**
     * Resolve a whole processing chunk with one Viva query.
     *
     * @param list<int> $registrationIds
     * @return array<int,array{eligible:bool,track:?string,written_track:?string,status:string,reason_code:?string,reason_message:?string}>
     */
    public function resolveMany(array $registrationIds): array
    {
        if ($this->finalizedProcessingRunId === null) {
            $this->assertVivaReady();
        }

        $ids = array_values(array_unique(array_map('intval', $registrationIds)));
        if ($ids === []) {
            return [];
        }

        $rows = VivaResult::query()
            ->whereIn('registration_id', $ids)
            ->where('processing_run_id', $this->finalizedProcessingRunId)
            ->get(['registration_id', 'status', 'viva_result_status', 'written_qualified_track'])
            ->keyBy('registration_id');

        $resolved = [];

        foreach ($ids as $registrationId) {
            $viva = $rows->get($registrationId);
            $writtenTrack = $this->enumOrString($viva?->written_qualified_track);
            $status = $this->enumOrString($viva?->status);
            $resultStatus = $this->enumOrString($viva?->viva_result_status);

            // Viva finalization is examination/run-level. Individual VivaResult
            // rows are tied to the finalized processing_run_id; they are not
            // required to carry a per-row finalized_at timestamp.
            $track = null;
            $candidateStatus = 'valid';
            $reasonCode = null;
            $reasonMessage = null;

            if (! $viva) {
                $candidateStatus = 'not_applicable_due_to_missing_viva_result';
                $reasonCode = 'CANDIDATE_MISSING_VIVA_RESULT';
                $reasonMessage = 'No Viva result exists for the candidate in the current finalized Viva processing run.';
            } elseif ($status !== strtoupper(VivaCandidateStatus::Active->value)) {
                $candidateStatus = 'not_applicable_due_to_inactive_viva_result';
                $reasonCode = 'CANDIDATE_INACTIVE_IN_VIVA';
                $reasonMessage = 'Candidate is not ACTIVE in the current finalized Viva result.';
            } elseif ($resultStatus === strtoupper(VivaResultStatus::Fail->value)) {
                $candidateStatus = 'not_applicable_due_to_fail_in_viva';
                $reasonCode = 'CANDIDATE_FAILED_IN_VIVA';
                $reasonMessage = 'Candidate failed in the current finalized Viva result.';
            } elseif ($resultStatus !== strtoupper(VivaResultStatus::Pass->value)) {
                $candidateStatus = 'not_applicable_due_to_inactive_viva_result';
                $reasonCode = 'CANDIDATE_INACTIVE_IN_VIVA';
                $reasonMessage = 'Candidate does not have a finalized Viva PASS result.';
            } else {
                $track = match ($writtenTrack) {
                    'GG', 'GN' => 'general',
                    'TT', 'T' => 'technical',
                    'GT' => 'both',
                    default => null,
                };

                if ($track === null) {
                    $candidateStatus = 'not_applicable_due_to_unresolved_written_track';
                    $reasonCode = 'CANDIDATE_UNRESOLVED_WRITTEN_TRACK';
                    $reasonMessage = 'Finalized Viva PASS exists, but the surviving Written qualified track could not be resolved.';
                }
            }

            $resolved[$registrationId] = [
                'eligible' => $track !== null,
                'track' => $track,
                'written_track' => $writtenTrack,
                'status' => $track !== null ? 'valid' : $candidateStatus,
                'reason_code' => $reasonCode,
                'reason_message' => $reasonMessage,
            ];
        }

        return $resolved;
    }

    private function enumOrString(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return strtoupper((string) $value->value);
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }
}
