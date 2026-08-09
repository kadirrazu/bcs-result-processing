<?php

namespace Tests\Feature\MasterData;

use App\Services\MasterDataImport\MasterDataSpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class MasterDataSpreadsheetReaderHeaderTest extends TestCase
{
    public function test_cadre_master_headers_are_read_using_the_current_contract(): void
    {
        $expected = config('master-data-imports.cadre-masters.headers');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Template');
        $sheet->fromArray($expected, null, 'A1');
        $sheet->fromArray([110, 'ADMN', 'BCS (Administration)', 'বিসিএস (প্রশাসন)', 'Assistant Commissioner', 'সহকারী কমিশনার', 'GG', 10, 1], null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'cadre-header-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $rows = app(MasterDataSpreadsheetReader::class)->read($path, $expected);
            $this->assertCount(1, $rows);
            $this->assertSame('ADMN', $rows[0]['data']['cadre_abbr']);
        } finally {
            @unlink($path);
        }
    }
}
