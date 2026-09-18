<?php

namespace App\Services\NonCadre\Circular;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class NonCadreCircularSpreadsheetService
{
    public const HEADERS = ['post_grade','post_serial','post_sub_serial','ministry','ministry_bn','entity','entity_bn','post_title','post_title_bn','post_code','post_count','bachelor_subject_codes','status','special_requirement','special_requirement_note'];

    public function __construct(private readonly NonCadreCircularValidator $validator) {}

    public function template(): BinaryFileResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Non-Cadre Circular');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', 'O') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $path = tempnam(sys_get_temp_dir(), 'non-cadre-circular-').'.xlsx';
        (new Xlsx($book))->save($path);
        return response()->download($path, 'non-cadre-circular-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function stage(UploadedFile $file, ?int $actorId): int
    {
        $path = $file->getRealPath();
        $hash = hash_file('sha256', $path) ?: null;
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        if ($rows === []) throw new RuntimeException('The spreadsheet is empty.');
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($rows));
        while ($headers !== [] && end($headers) === '') array_pop($headers);
        if ($headers !== self::HEADERS) throw new RuntimeException('Spreadsheet headers do not match the Non-Cadre Circular template. Expected: '.implode(', ', self::HEADERS));

        return DB::connection('exam')->transaction(function () use ($file, $hash, $rows, $headers, $actorId): int {
            $id = DB::connection('exam')->table('non_cadre_circular_imports')->insertGetId([
                'status' => 'staged', 'source_filename' => $file->getClientOriginalName(), 'source_hash' => $hash,
                'uploaded_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $total = $valid = $invalid = 0; $seen = [];
            foreach ($rows as $offset => $values) {
                if (collect($values)->every(fn ($v) => $v === null || trim((string) $v) === '')) continue;
                $total++;
                $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
                $row = array_combine($headers, $values);
                $result = $this->validator->validate($row);
                $code = (string) ($result['data']['post_code'] ?? '');
                if ($code !== '' && isset($seen[$code])) {
                    $result['valid'] = false; $result['errors'][] = "Duplicate post_code $code in this import (first seen at row {$seen[$code]}).";
                } elseif ($code !== '') $seen[$code] = $offset + 2;
                $result['valid'] ? $valid++ : $invalid++;
                DB::connection('exam')->table('non_cadre_circular_import_rows')->insert([
                    'import_id' => $id, 'row_number' => $offset + 2,
                    'raw_data' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'normalized_data' => json_encode($result['data'], JSON_UNESCAPED_UNICODE),
                    'validation_status' => $result['valid'] ? 'valid' : 'invalid',
                    'validation_errors' => $result['errors'] === [] ? null : json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::connection('exam')->table('non_cadre_circular_imports')->where('id', $id)->update([
                'status' => $invalid ? 'needs_review' : 'validated', 'total_rows' => $total, 'valid_rows' => $valid, 'invalid_rows' => $invalid, 'updated_at' => now(),
            ]);
            return $id;
        });
    }
}
