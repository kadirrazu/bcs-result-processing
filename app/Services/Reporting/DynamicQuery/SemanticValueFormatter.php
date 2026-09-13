<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;

final class SemanticValueFormatter
{
    /** @var array<string,string>|null */
    private ?array $cadreLabels = null;

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $fieldIds
     * @return array<int,array<string,mixed>>
     */
    public function formatRows(array $rows, array $fieldIds): array
    {
        if ($rows === []) {
            return [];
        }

        $registry = (array) config('dynamic-reports.fields', []);

        foreach ($rows as &$row) {
            foreach ($fieldIds as $fieldId) {
                if (! array_key_exists($fieldId, $row)) {
                    continue;
                }

                $meta = $registry[$fieldId] ?? null;
                if (! is_array($meta)) {
                    continue;
                }

                $row[$fieldId] = $this->formatValue($row[$fieldId], $meta);
            }
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $meta */
    public function formatValue(mixed $value, array $meta): mixed
    {
        $formatter = (string) ($meta['formatter'] ?? '');

        if ($formatter === 'cadre') {
            if ($value === null || $value === '') {
                return 'Not Allocated';
            }

            return $this->cadreLabels()[(string) $value] ?? (string) $value;
        }

        $options = (array) ($meta['options'] ?? []);
        if ($options !== [] && $value !== null && array_key_exists((string) $value, $options)) {
            return (string) $options[(string) $value];
        }

        return $value;
    }

    /** @return array<string,string> */
    private function cadreLabels(): array
    {
        if ($this->cadreLabels !== null) {
            return $this->cadreLabels;
        }

        $labels = [];
        CadreMaster::query()
            ->select(['cadre_code', 'cadre_abbr', 'cadre_name'])
            ->get()
            ->each(function (CadreMaster $cadre) use (&$labels): void {
                $parts = array_values(array_filter([
                    (string) $cadre->cadre_code,
                    trim((string) $cadre->cadre_abbr),
                    trim((string) $cadre->cadre_name),
                ], static fn (string $part): bool => $part !== ''));
                $labels[(string) $cadre->cadre_code] = implode(' - ', $parts);
            });

        CadreSubMaster::query()
            ->with('parentCadre:id,cadre_name')
            ->select(['id', 'parent_cadre_id', 'sub_cadre_code', 'sub_cadre_abbr', 'post_name'])
            ->get()
            ->each(function (CadreSubMaster $subCadre) use (&$labels): void {
                $parts = array_values(array_filter([
                    (string) $subCadre->sub_cadre_code,
                    trim((string) $subCadre->sub_cadre_abbr),
                    trim((string) ($subCadre->parentCadre?->cadre_name ?? '')),
                    trim((string) $subCadre->post_name),
                ], static fn (string $part): bool => $part !== ''));
                $labels[(string) $subCadre->sub_cadre_code] = implode(' - ', $parts);
            });

        return $this->cadreLabels = $labels;
    }
}
