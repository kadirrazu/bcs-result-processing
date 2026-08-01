<?php

namespace Tests\Unit\Written;

use Tests\TestCase;

final class WrittenTemplateContractTest extends TestCase
{
    public function test_written_headers_match_locked_excel_contract(): void
    {
        $this->assertSame([
            'user', 'reg', 's001_mark', 's002_mark', 's003_mark', 's005_mark',
            's007_mark', 's008_mark', 's009_mark', 's010_mark', 'prs_code',
            'prs_mark', 'data_source_note',
        ], config('written.headers'));
    }

    public function test_data_source_note_has_no_status_mapping_configuration(): void
    {
        $this->assertArrayNotHasKey('data_source_status_mapping', config('written'));
        $this->assertArrayNotHasKey('data_source_note_mapping', config('written'));
    }
}
