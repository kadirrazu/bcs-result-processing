<?php

namespace App\Services\MasterDataImport;

use App\Enums\CadreType;
use App\Models\Division;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Normalize and validate one spreadsheet row without mutating the database. */
final class MasterDataRowValidator
{
    /** @return array{valid:bool,data:array<string,mixed>,errors:list<string>} */
    public function validate(MasterDataImportDefinition $definition, array $row): array
    {
        $data = collect($row)->map(fn ($value) => is_string($value) ? trim($value) : $value)->all();
        $data = $this->normalize($definition, $data);

        $rules = match ($definition->key) {
            'cadre-masters' => [
                'cadre_code' => ['required', 'integer', 'min:1'],
                'cadre_abbr' => ['required', 'string', 'max:20'],
                'cadre_title' => ['required', 'string', 'max:255'],
                'cadre_title_bn' => ['required', 'string', 'max:255'],
                'cadre_type' => ['required', Rule::enum(CadreType::class)],
                'display_order' => ['required', 'integer', 'min:0'],
                'is_active' => ['required', 'boolean'],
            ],
            'divisions' => [
                'code' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:120'],
                'name_bn' => ['nullable', 'string', 'max:150'],
                'is_active' => ['required', 'boolean'],
            ],
            'districts' => [
                'code' => ['required', 'integer', 'min:1'],
                'division_code' => ['required', 'integer', 'min:1', Rule::exists((new Division)->getTable(), 'code')->where('is_active', true)],
                'name' => ['required', 'string', 'max:120'],
                'name_bn' => ['nullable', 'string', 'max:150'],
                'is_active' => ['required', 'boolean'],
            ],
            'universities' => [
                'code' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'string', 'max:255'],
                'name_bn' => ['nullable', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [
                'subject_code' => ['required', 'string', 'max:30'],
                'subject_name' => ['required', 'string', 'max:255'],
                'is_active' => ['required', 'boolean'],
            ],
        };

        $validator = Validator::make($data, $rules);

        return [
            'valid' => ! $validator->fails(),
            'data' => $data,
            'errors' => $validator->errors()->all(),
        ];
    }

    private function normalize(MasterDataImportDefinition $definition, array $data): array
    {
        $data['is_active'] = $this->boolean($data['is_active'] ?? true);

        if ($definition->key === 'cadre-masters') {
            $data['cadre_abbr'] = strtoupper((string) ($data['cadre_abbr'] ?? ''));
            $data['cadre_type'] = strtoupper((string) ($data['cadre_type'] ?? ''));
            $data['display_order'] = (int) ($data['display_order'] ?? 0);
            $data['cadre_code'] = is_numeric($data['cadre_code'] ?? null) ? (int) $data['cadre_code'] : $data['cadre_code'];
        } elseif (in_array($definition->key, ['divisions', 'districts', 'universities'], true)) {
            $data['code'] = is_numeric($data['code'] ?? null) ? (int) $data['code'] : $data['code'];
            $data['name_bn'] = $this->nullableText($data['name_bn'] ?? null);
            if ($definition->key === 'districts') {
                $data['division_code'] = is_numeric($data['division_code'] ?? null)
                    ? (int) $data['division_code']
                    : $data['division_code'];
            }
        } else {
            $data['subject_code'] = strtoupper((string) ($data['subject_code'] ?? ''));
        }

        return $data;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'active'], true);
    }
}
