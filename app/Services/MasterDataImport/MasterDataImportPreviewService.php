<?php

namespace App\Services\MasterDataImport;

/** Build a row-level preview, including validation and duplicate status. */
final readonly class MasterDataImportPreviewService
{
    public function __construct(private MasterDataSpreadsheetReader $reader, private MasterDataRowValidator $validator) {}

    public function preview(MasterDataImportDefinition $definition, string $path): array
    {
        $model = $definition->model();
        $key = $definition->uniqueBy();
        $result = [];
        foreach ($this->reader->read($path, $definition->headers()) as $row) {
            $validated = $this->validator->validate($definition, $row['data']);
            $exists = $validated['valid'] && $model::query()->where($key, $validated['data'][$key])->exists();
            $result[] = [...$row, ...$validated, 'exists' => $exists];
        }

        return $result;
    }
}
