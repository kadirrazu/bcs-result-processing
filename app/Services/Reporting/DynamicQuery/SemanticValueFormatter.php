<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Services\ChoiceOptimization\FinalAllocationReadyChoiceService;

final class SemanticValueFormatter
{
    /** @var array<int,array<string,bool>>|null */
    private ?array $activeExclusionMap = null;

    public function __construct(
        private readonly FinalAllocationReadyChoiceService $finalChoices,
        private readonly SemanticLookupRegistry $lookups,
    ) {}

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

        if (isset($meta['lookup'])) {
            if ((string) $meta['lookup'] === 'cadre_effective' && ($value === null || $value === '')) {
                return 'Not Allocated';
            }

            return $this->lookups->label((string) $meta['lookup'], $value);
        }

        if ($formatter === 'choice_csv') {
            $codes = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $code): bool => $code !== ''));
            if ($codes === []) return '—';
            return implode(' > ', array_map(fn ($code): string => $this->cadreLabels()[(string) $code] ?? (string) $code, $codes));
        }

        if ($formatter === 'choice_codes') {
            $codes = is_array($value) ? $value : json_decode((string) $value, true);
            if (! is_array($codes) || $codes === []) return '—';
            return implode(' > ', array_map(fn ($code): string => $this->cadreLabels()[(string) $code] ?? (string) $code, array_values($codes)));
        }

        if ($formatter === 'final_choice_codes') {
            $payload = is_array($value) ? $value : json_decode((string) $value, true);
            if (! is_array($payload)) return '—';
            $registrationId = (int) ($payload['registration_id'] ?? 0);
            $codes = array_values(array_map('strval', (array) ($payload['codes'] ?? [])));
            if ($registrationId > 0) {
                $excluded = $this->activeExclusionMap()[$registrationId] ?? [];
                $codes = array_values(array_filter($codes, static fn ($code): bool => ! isset($excluded[(string) $code])));
            }
            if ($codes === []) return '—';
            return implode(' > ', array_map(fn ($code): string => $this->cadreLabels()[(string) $code] ?? (string) $code, $codes));
        }

        $options = (array) ($meta['options'] ?? []);
        if ($options !== [] && $value !== null && array_key_exists((string) $value, $options)) {
            return (string) $options[(string) $value];
        }

        return $value;
    }

    /** @return array<int,array<string,bool>> */
    private function activeExclusionMap(): array
    {
        if ($this->activeExclusionMap !== null) return $this->activeExclusionMap;
        $map = [];
        foreach ($this->finalChoices->activeExclusions() as $registrationId => $codes) {
            $map[(int) $registrationId] = array_fill_keys(
                $codes->map(static fn ($code): string => (string) $code)->all(),
                true
            );
        }
        return $this->activeExclusionMap = $map;
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
