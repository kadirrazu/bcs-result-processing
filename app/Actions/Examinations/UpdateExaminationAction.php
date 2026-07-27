<?php

namespace App\Actions\Examinations;

use App\Data\ExaminationData;
use App\Models\Examination;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update one central examination registry entry while preserving active-context safety.
 */
final class UpdateExaminationAction
{
    public function __construct(private readonly ExaminationContext $context) {}

    public function execute(Examination $examination, ExaminationData $data): Examination
    {
        if ($this->context->is($examination) && ! $data->isEnabled) {
            throw ValidationException::withMessages([
                'is_enabled' => 'The currently active examination cannot be disabled.',
            ]);
        }

        return DB::transaction(function () use ($examination, $data): Examination {
            $examination->update($data->toArray());

            return $examination->refresh();
        });
    }
}
