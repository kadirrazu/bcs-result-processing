<?php

namespace App\Services\Circular;

use App\Models\CircularImportBatch;
use App\Models\CircularImportStaging;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CircularSpreadsheetService
{
    public function __construct(private readonly CircularEntryValidator $validator) {}

    public function template(): BinaryFileResponse
    {
        $headers = config('circular.headers');
        $sample = [
            1, null, 110, null, 'GG', 200, null, null, 'ACTIVE', null,
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Circular Import');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($sample, null, 'A2');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'circular-template-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'circular-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function stage(UploadedFile $file, int $userId): CircularImportBatch
    {
        $storedPath = $file->store('circular-imports');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $sheet = IOFactory::load($absolutePath)->getActiveSheet();
            $raw = $sheet->toArray(null, true, true, false);
            if ($raw === []) {
                throw new RuntimeException('The spreadsheet is empty.');
            }

            $headers = $this->normalizeHeaders(array_shift($raw));
            $expected = config('circular.headers');
            if ($headers !== $expected) {
                throw new RuntimeException(
                    'Spreadsheet headers do not match the Circular template. Expected: '.implode(', ', $expected).
                    ' | Found: '.implode(', ', $headers)
                );
            }

            return DB::connection('exam')->transaction(function () use ($file, $storedPath, $raw, $headers, $userId): CircularImportBatch {
                $batch = CircularImportBatch::query()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'status' => 'staged',
                    'uploaded_by' => $userId,
                ]);

                $total = $valid = $invalid = 0;
                foreach ($raw as $index => $values) {
                    if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                        continue;
                    }
                    $total++;
                    $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
                    $row = array_combine($headers, $values);
                    $result = $this->validator->validate($row);
                    $result['valid'] ? $valid++ : $invalid++;

                    CircularImportStaging::query()->create([
                        'batch_id' => $batch->id,
                        'row_number' => $index + 2,
                        'raw_data' => $row,
                        'normalized_data' => $result['data'],
                        'validation_status' => $result['valid'] ? 'valid' : 'invalid',
                        'validation_errors' => $result['errors'] ?: null,
                    ]);
                }

                $batch->update([
                    'status' => $invalid > 0 ? 'needs_review' : 'validated',
                    'total_rows' => $total,
                    'valid_rows' => $valid,
                    'invalid_rows' => $invalid,
                ]);

                return $batch->fresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }
    }

    private function normalizeHeaders(array $headers): array
    {
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }
        return $headers;
    }
}
