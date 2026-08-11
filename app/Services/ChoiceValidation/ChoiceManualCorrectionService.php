<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationManualCorrection;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ChoiceManualCorrectionService
{
    public function __construct(
        private readonly ChoiceColumnResolver $columns,
        private readonly ChoiceEffectiveSourceResolver $effectiveSource,
    ) {}

    /** @param array<string,mixed> $input */
    public function correct(ChoiceValidationResult $result, array $input, User $actor, Request $request): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A correction reason is required.']);
        }

        /** @var ChoiceSource $source */
        $source = ChoiceSource::query()->findOrFail($result->choice_source_id);
        $before = $this->effectiveSource->snapshot($source);
        $after = [
            'user' => $source->user_id,
            'reg' => $source->reg,
        ];

        $seenBlank = false;
        $choiceCount = 0;
        $changedPositions = [];

        foreach ($this->columns->choiceColumns() as $index => $column) {
            $value = trim((string) ($input[$column] ?? ''));
            $value = $value === '' ? null : $value;
            $after[$column] = $value;

            if ($value === null) {
                $seenBlank = true;
            } else {
                $choiceCount++;
                if ($seenBlank) {
                    throw ValidationException::withMessages([
                        $column => 'CHOICE_SEQUENCE_GAP: a choice cannot appear after an earlier blank choice position.',
                    ]);
                }
            }

            $beforeValue = trim((string) (($before[$column] ?? '') ?: ''));
            $afterValue = trim((string) ($value ?? ''));
            if ($beforeValue !== $afterValue) {
                $changedPositions[] = $index + 1;
            }
        }

        if ($choiceCount < 1) {
            throw ValidationException::withMessages([
                'opt_01' => 'NO_CHOICE_PROVIDED: at least one raw choice is required.',
            ]);
        }

        if ($changedPositions === []) {
            return ['changed' => false, 'correction' => null];
        }

        $correction = DB::connection('exam')->transaction(function () use ($source, $result, $before, $after, $changedPositions, $reason, $actor, $request) {
            $correction = ChoiceValidationManualCorrection::query()->create([
                'choice_source_id' => $source->id,
                'registration_id' => $source->registration_id,
                'source_version' => $source->source_version,
                'validation_version' => $result->validation_version,
                'before_snapshot' => $before,
                'corrected_snapshot' => $after,
                'changed_positions' => $changedPositions,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $state = ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
            $state->update([
                'status' => 'validation_needs_review',
                'is_stale' => true,
                'stale_reason' => "Choice source for REG {$source->reg} was manually corrected and requires candidate revalidation.",
                'finalized_validation_version' => null,
                'latest_finalization_run_id' => null,
                'finalized_at' => null,
            ]);

            ChoiceValidationProcessingAudit::query()->create([
                'action' => 'CHOICE_MANUAL_CORRECTION',
                'actor_id' => $actor->id,
                'actor_name' => $actor->name ?? null,
                'reason' => $reason,
                'summary' => [
                    'reg' => $source->reg,
                    'choice_source_id' => $source->id,
                    'validation_result_id' => $result->id,
                    'validation_version' => $result->validation_version,
                    'changed_positions' => $changedPositions,
                    'before' => $before,
                    'after' => $after,
                    'raw_import_preserved' => true,
                ],
                'created_at' => now(),
            ]);

            return $correction;
        }, 3);

        $logger = Log::build([
            'driver' => 'daily',
            'path' => storage_path('logs/choice-validation-corrections.log'),
            'level' => 'info',
            'days' => 30,
        ]);
        $logger->info('CHOICE_MANUAL_CORRECTION', [
            'reg' => $source->reg,
            'source_version' => $source->source_version,
            'validation_version' => $result->validation_version,
            'changed_positions' => $changedPositions,
            'reason' => $reason,
            'actor_id' => $actor->id,
        ]);

        return ['changed' => true, 'correction' => $correction];
    }
}
