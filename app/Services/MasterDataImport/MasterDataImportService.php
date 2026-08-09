<?php

namespace App\Services\MasterDataImport;

use App\Models\CadreMaster;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Persist a confirmed import atomically according to its duplicate mode. */
final class MasterDataImportService
{
    public function import(MasterDataImportDefinition $definition, array $rows, string $mode): array
    {
        if (! in_array($mode, ['insert', 'update', 'upsert'], true)) {
            throw new InvalidArgumentException('Unsupported import mode.');
        }

        $model = $definition->model();
        $key = $definition->uniqueBy();
        $summary = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        DB::connection((new $model)->getConnectionName())->transaction(function () use ($definition, $rows, $mode, $model, $key, &$summary): void {
            foreach ($rows as $row) {
                if (! ($row['valid'] ?? false)) {
                    $summary['failed']++;
                    continue;
                }

                $data = $row['data'];
                if ($definition->key === 'cadre-sub-masters') {
                    $parent = CadreMaster::query()->where('cadre_code', (int) $data['parent_cadre_code'])->firstOrFail();
                    $data['parent_cadre_id'] = $parent->id;
                    unset($data['parent_cadre_code']);
                }

                $existing = $model::query()->where($key, $data[$key])->first();

                if ($existing && $mode === 'insert') {
                    $summary['skipped']++;
                    continue;
                }

                if (! $existing && $mode === 'update') {
                    $summary['skipped']++;
                    continue;
                }

                if ($existing) {
                    $existing->update($data);
                    $summary['updated']++;
                } else {
                    $model::query()->create($data);
                    $summary['inserted']++;
                }
            }
        });

        return $summary;
    }
}
