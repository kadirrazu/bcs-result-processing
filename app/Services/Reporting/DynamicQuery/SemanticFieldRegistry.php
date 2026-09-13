<?php

namespace App\Services\Reporting\DynamicQuery;

use Illuminate\Validation\ValidationException;

final class SemanticFieldRegistry
{
    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return (array) config('dynamic-reports.fields', []);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        $field = $this->all()[$id] ?? null;
        if (! is_array($field)) {
            throw ValidationException::withMessages(['query' => "Unsupported report field [{$id}]."]);
        }

        return $field;
    }

    /** @return array<int,array<string,mixed>> */
    public function browserFields(): array
    {
        $rows = [];
        foreach ($this->all() as $id => $field) {
            if (! ($field['selectable'] ?? false)) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'label' => (string) ($field['label'] ?? $id),
                'module' => (string) ($field['module'] ?? 'Other'),
                'type' => (string) ($field['type'] ?? 'string'),
                'operators' => array_values((array) ($field['operators'] ?? [])),
                'options' => (array) ($field['options'] ?? []),
                'sortable' => (bool) ($field['sortable'] ?? false),
                'groupable' => (bool) ($field['groupable'] ?? false),
                'aggregates' => array_values((array) ($field['aggregates'] ?? [])),
            ];
        }

        return $rows;
    }
}
