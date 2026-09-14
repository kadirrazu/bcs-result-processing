<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\District;
use App\Models\Division;
use App\Models\Gender;
use App\Models\PostRelatedSubject;
use App\Models\University;

final class SemanticLookupRegistry
{
    /** @var array<string,array<string,string>> */
    private array $cache = [];

    /** @return array<string,string> */
    public function options(string $lookup): array
    {
        return $this->cache[$lookup] ??= match ($lookup) {
            'gender' => Gender::query()->where('is_active', true)->orderBy('code')->pluck('name', 'code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'district' => District::query()->where('is_active', true)->orderBy('name')->pluck('name', 'code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'division' => Division::query()->where('is_active', true)->orderBy('name')->pluck('name', 'code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'university' => University::query()->where('is_active', true)->orderBy('name')->pluck('name', 'code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'bachelor_subject' => BachelorSubject::query()->where('is_active', true)->orderBy('subject_name')->pluck('subject_name', 'subject_code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'prs' => PostRelatedSubject::query()->where('is_active', true)->orderBy('subject_name')->pluck('subject_name', 'subject_code')->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all(),
            'cadre_effective' => $this->cadreOptions(),
            default => [],
        };
    }

    public function label(string $lookup, mixed $value): mixed
    {
        if ($value === null || $value === '') return $value;
        return $this->options($lookup)[(string) $value] ?? $value;
    }

    /** @return array<string,string> */
    private function cadreOptions(): array
    {
        $options = [];
        CadreMaster::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['cadre_code', 'cadre_abbr', 'cadre_name'])
            ->each(function (CadreMaster $cadre) use (&$options): void {
                $parts = array_values(array_filter([
                    trim((string) $cadre->cadre_abbr),
                    trim((string) $cadre->cadre_name),
                ], static fn (string $v): bool => $v !== ''));
                $options[(string) $cadre->cadre_code] = implode(' - ', $parts) ?: (string) $cadre->cadre_code;
            });

        CadreSubMaster::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get(['sub_cadre_code', 'sub_cadre_abbr', 'post_name'])
            ->each(function (CadreSubMaster $sub) use (&$options): void {
                $parts = array_values(array_filter([
                    trim((string) $sub->sub_cadre_abbr),
                    trim((string) $sub->post_name),
                ], static fn (string $v): bool => $v !== ''));
                $options[(string) $sub->sub_cadre_code] = implode(' - ', $parts) ?: (string) $sub->sub_cadre_code;
            });

        return $options;
    }
}
