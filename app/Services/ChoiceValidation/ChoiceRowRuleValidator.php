<?php

namespace App\Services\ChoiceValidation;

use App\Enums\ChoiceValidationReason;

final class ChoiceRowRuleValidator
{
    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    /**
     * @param array<string,mixed> $raw
     * @return array{choices:list<array{position:int,column:string,raw:?string,code:?string}>, errors:list<string>, warnings:list<string>, choice_count:int}
     */
    public function validate(array $raw, ?int $maximum = null): array
    {
        $choices = [];
        $errors = [];
        $warnings = [];
        $seenBlank = false;
        $populated = 0;

        foreach ($this->columns->choiceColumns($maximum) as $index => $column) {
            $position = $index + 1;
            $rawValue = $this->rawText($raw[$column] ?? null);
            $code = $this->normalizeCode($rawValue);

            if ($code === null) {
                $seenBlank = true;
            } else {
                $populated++;
                if ($seenBlank) {
                    $errors[] = sprintf('%s: %s contains a choice after an earlier blank position.', ChoiceValidationReason::ChoiceSequenceGap->value, $column);
                }
            }

            $choices[] = ['position'=>$position,'column'=>$column,'raw'=>$rawValue,'code'=>$code];
        }

        if ($populated === 0) {
            $errors[] = ChoiceValidationReason::NoChoiceProvided->value.': at least one raw choice is required.';
        }

        return ['choices'=>$choices,'errors'=>array_values(array_unique($errors)),'warnings'=>array_values(array_unique($warnings)),'choice_count'=>$populated];
    }

    private function rawText(mixed $value): ?string { if($value===null)return null;$value=trim((string)$value);return $value===''?null:$value; }
    private function normalizeCode(?string $value): ?string { if($value===null)return null;$value=strtoupper(trim($value));if(preg_match('/^\d+\.0$/',$value)===1)$value=substr($value,0,-2);return $value===''?null:$value; }
}
