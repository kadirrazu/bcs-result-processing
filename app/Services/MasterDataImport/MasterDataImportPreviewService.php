<?php

namespace App\Services\MasterDataImport;

/** Build a row-level preview, including validation and duplicate status. */
final readonly class MasterDataImportPreviewService
{
    public function __construct(
        private MasterDataSpreadsheetReader $reader,
        private MasterDataRowValidator $validator,
    ) {}

    public function preview(MasterDataImportDefinition $definition, string $path): array
    {
        $model = $definition->model();
        $seen = [];
        $result = [];

        foreach ($this->reader->read($path, $definition->headers()) as $row) {
            $validated = $this->validator->validate($definition, $row['data']);
            $errors = $validated['errors'];
            $existing = null;

            if ($validated['valid']) {
                foreach ($definition->allUniqueColumns() as $column) {
                    $value = $validated['data'][$column] ?? null;
                    $signature = $column.'|'.mb_strtolower((string) $value);

                    if (isset($seen[$signature])) {
                        $errors[] = sprintf('%s duplicates spreadsheet row %d.', $column, $seen[$signature]);
                    } else {
                        $seen[$signature] = $row['row_number'];
                    }
                }

                if ($errors === []) {
                    $primary = $definition->uniqueBy();
                    $existing = $model::query()->where($primary, $validated['data'][$primary])->first();

                    foreach ($definition->additionalUniqueBy() as $column) {
                        $conflict = $model::query()->where($column, $validated['data'][$column])->first();

                        if ($conflict && (! $existing || ! $conflict->is($existing))) {
                            $errors[] = sprintf('%s already belongs to another record.', $column);
                        }
                    }
                }
            }

            $result[] = [
                ...$row,
                ...$validated,
                'valid' => $validated['valid'] && $errors === [],
                'errors' => $errors,
                'exists' => $existing !== null,
            ];
        }

        return $result;
    }
}
