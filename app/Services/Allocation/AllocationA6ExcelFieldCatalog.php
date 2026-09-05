<?php

namespace App\Services\Allocation;

use Illuminate\Validation\ValidationException;

/**
 * Allocation-specific field catalogue for A6 dynamic XLSX exports.
 * The generic spreadsheet writer intentionally knows nothing about these fields.
 */
final class AllocationA6ExcelFieldCatalog
{
    /** @return array<string,array{label:string,fields:array<string,string>}> */
    public function groups(): array
    {
        return [
            'registration' => ['label' => 'Registration', 'fields' => [
                'registration.reg'=>'Reg','registration.user_id'=>'User ID','registration.name'=>'Name',
                'registration.father_name'=>'Father Name','registration.mother_name'=>'Mother Name','registration.dob'=>'DOB',
                'registration.sex_code'=>'Sex Code','registration.district_code'=>'District Code','registration.cadre_category'=>'Cadre Category',
                'registration.bachelor_subject'=>'Bachelor Subject','registration.prs'=>'PRS','registration.cff'=>'CFF',
                'registration.em'=>'EM','registration.phc'=>'PHC',
            ]],
            'preliminary' => ['label' => 'Preliminary', 'fields' => [
                'preliminary.mark'=>'Preliminary Mark','preliminary.result'=>'Preliminary Result',
            ]],
            'written' => ['label' => 'Written', 'fields' => [
                'written.track'=>'Written Qualified Track','written.general_total'=>'General Written','written.technical_total'=>'Technical Written',
                'written.general_result'=>'General Written Result','written.technical_result'=>'Technical Written Result',
            ]],
            'viva' => ['label' => 'Viva', 'fields' => [
                'viva.mark'=>'Viva Mark','viva.result'=>'Viva Result',
            ]],
            'tabulation' => ['label' => 'Tabulation', 'fields' => [
                'tabulation.general_grand_total'=>'General Grand Total','tabulation.technical_grand_total'=>'Technical Grand Total',
                'tabulation.general_merit_eligible'=>'General Merit Eligible','tabulation.technical_merit_eligible'=>'Technical Merit Eligible',
            ]],
            'choice' => ['label' => 'Choice', 'fields' => [
                'choice.registration'=>'Registration Choices','choice.validated'=>'Validated Choices','choice.omr'=>'OMR Choices','choice.effective'=>'Effective Allocation-ready Choices',
            ]],
            'merit' => ['label' => 'Merit', 'fields' => [
                'merit.common'=>'Common Merit Position','merit.general'=>'General Merit Position','merit.technical'=>'Technical Merit Position',
            ]],
            'allocation' => ['label' => 'Allocation', 'fields' => [
                'allocation.cadre'=>'Allocated Cadre Code','allocation.basis'=>'Allocation Basis','allocation.choice_position'=>'Choice Position',
                'allocation.movement'=>'Movement','allocation.merit_position'=>'Cadre Merit Position',
            ]],
            'a5' => ['label' => 'A5 Validity', 'fields' => [
                'a5.bachelor'=>'A5 Bachelor','a5.prs'=>'A5 PRS','a5.technical'=>'A5 Technical','a5.quota'=>'A5 Quota','a5.overall'=>'A5 Overall',
            ]],
        ];
    }

    /** Hidden/automatic companion fields that remain available for headers/audit/value generation. @return array<string,string> */
    public function companionFields(): array
    {
        return [
            'registration.sex' => 'Sex',
            'registration.district_name' => 'District Name',
            'choice.registration_abbr' => 'Registration Choice Abbreviations',
            'choice.validated_abbr' => 'Validated Choice Abbreviations',
            'choice.omr_abbr' => 'OMR Choice Abbreviations',
            'choice.effective_abbr' => 'Effective Choice Abbreviations',
            'allocation.cadre_abbr' => 'Cadre Abbreviation',
        ];
    }

    /** @return array<string,string> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->groups() as $group) {
            $fields += $group['fields'];
        }
        return $fields + $this->companionFields();
    }

    /** @return array<int,string> */
    public function defaultKeys(): array
    {
        $keys = [];
        foreach ($this->groups() as $group) {
            array_push($keys, ...array_keys($group['fields']));
        }
        return $this->expandSelection($keys);
    }

    /** @param array<int,mixed> $selected @return array<int,string> */
    public function validateSelection(array $selected): array
    {
        $selectable = $this->fields();
        $clean = collect($selected)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
        if ($clean === []) {
            throw ValidationException::withMessages(['fields' => 'Select at least one Excel field.']);
        }
        $invalid = array_values(array_diff($clean, array_keys($selectable)));
        if ($invalid !== []) {
            throw ValidationException::withMessages(['fields' => 'One or more selected Excel fields are not supported.']);
        }
        return $this->expandSelection($clean);
    }

    /** @param array<int,string> $fields @return array<int,string> */
    private function expandSelection(array $fields): array
    {
        $companions = [
            'registration.sex_code' => 'registration.sex',
            'registration.district_code' => 'registration.district_name',
            'choice.registration' => 'choice.registration_abbr',
            'choice.validated' => 'choice.validated_abbr',
            'choice.omr' => 'choice.omr_abbr',
            'choice.effective' => 'choice.effective_abbr',
            'allocation.cadre' => 'allocation.cadre_abbr',
        ];

        $expanded = [];
        foreach ($fields as $field) {
            $expanded[] = $field;
            if (isset($companions[$field])) {
                $expanded[] = $companions[$field];
            }
        }
        return array_values(array_unique($expanded));
    }
}
