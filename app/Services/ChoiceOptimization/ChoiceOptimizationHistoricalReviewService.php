<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationHistoricalSource;
use App\Models\ChoiceOptimizationProcessingAudit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceOptimizationHistoricalReviewService
{
    public function __construct(
        private readonly ChoiceOptimizationHistoricalStalenessService $staleness,
    ) {}
    public function resolve(
        ChoiceOptimizationHistoricalSource $source,
        ChoiceOptimizationHistoricalMatch $match,
        string $decision,
        string $reason,
        int $actorId,
    ): ChoiceOptimizationHistoricalMatch {
        if ((int) $match->historical_source_id !== (int) $source->id) {
            throw new RuntimeException('Historical match does not belong to this source.');
        }

        if ($match->match_status !== 'review' || $match->resolution_status !== 'pending') {
            throw new RuntimeException('Only an unresolved REVIEW match can be confirmed or rejected.');
        }

        if (! in_array($decision, ['confirm', 'reject'], true)) {
            throw new RuntimeException('Unsupported Historical Match review decision.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Administrative reason is required.');
        }

        DB::connection('exam')->transaction(function () use (
            $source,
            $match,
            $decision,
            $reason,
            $actorId,
        ): void {
            $locked = ChoiceOptimizationHistoricalMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->match_status !== 'review' || $locked->resolution_status !== 'pending') {
                throw new RuntimeException('This Historical Match review has already been resolved.');
            }

            if ($decision === 'confirm') {
                $locked->update([
                    'match_status' => 'matched',
                    'resolution_status' => 'operator_confirmed',
                    'resolution_reason' => $reason,
                    'resolved_by' => $actorId,
                    'resolved_at' => now(),
                ]);

                // If a defensive multiple-core review produced competing historical rows
                // for the same current candidate, confirming one candidate closes the rest.
                ChoiceOptimizationHistoricalMatch::query()
                    ->where('historical_source_id', (int) $source->id)
                    ->where('registration_id', (int) $locked->registration_id)
                    ->whereKeyNot($locked->id)
                    ->where('match_status', 'review')
                    ->where('resolution_status', 'pending')
                    ->update([
                        'match_status' => 'rejected',
                        'resolution_status' => 'competing_rejected',
                        'resolution_reason' => 'Automatically rejected because the operator confirmed another competing Historical BCS record for the same current candidate.',
                        'resolved_by' => $actorId,
                        'resolved_at' => now(),
                    ]);
            } else {
                $locked->update([
                    'match_status' => 'rejected',
                    'resolution_status' => 'operator_rejected',
                    'resolution_reason' => $reason,
                    'resolved_by' => $actorId,
                    'resolved_at' => now(),
                ]);
            }

            $this->refreshSourceMetrics($source);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => $decision === 'confirm'
                    ? 'HISTORICAL_MATCH_CONFIRMED'
                    : 'HISTORICAL_MATCH_REJECTED',
                'actor_id' => $actorId,
                'from_status' => 'review',
                'to_status' => $decision === 'confirm' ? 'matched' : 'rejected',
                'context' => [
                    'historical_source_id' => (int) $source->id,
                    'historical_match_id' => (int) $locked->id,
                    'current_registration_id' => (int) $locked->registration_id,
                    'current_reg' => (string) $locked->current_reg,
                    'previous_bcs_number' => (int) $locked->previous_bcs_number,
                    'repository_dataset_id' => (int) $locked->repository_dataset_id,
                    'repository_row_id' => (int) $locked->repository_row_id,
                    'previous_reg' => $locked->previous_reg,
                    'previous_name' => $locked->previous_name,
                    'previous_fname' => $locked->previous_fname,
                    'previous_cadre' => $locked->previous_cadre,
                    'decision' => $decision,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);
        });

        $this->staleness->markIfProduced(
            'Confirmed Historical Recommendation set changed after operator review.',
            $actorId,
            [
                'historical_source_id' => (int) $source->id,
                'historical_match_id' => (int) $match->id,
                'decision' => $decision,
            ],
        );

        return $match->refresh();
    }

    public function refreshSourceMetrics(ChoiceOptimizationHistoricalSource $source): ChoiceOptimizationHistoricalSource
    {
        $matched = ChoiceOptimizationHistoricalMatch::query()
            ->where('historical_source_id', (int) $source->id)
            ->where('match_status', 'matched')
            ->distinct()
            ->count('registration_id');

        $review = ChoiceOptimizationHistoricalMatch::query()
            ->where('historical_source_id', (int) $source->id)
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->distinct()
            ->count('registration_id');

        $noMatch = max(0, (int) $source->candidate_count - $matched - $review);

        $source->update([
            'matched_count' => $matched,
            'review_count' => $review,
            'no_match_count' => $noMatch,
        ]);

        return $source->refresh();
    }
}
