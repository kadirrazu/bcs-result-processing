<?php

namespace Tests\Unit\Viva;

use Tests\TestCase;

final class VivaTemplateContractTest extends TestCase
{
    public function test_mapping_headers_are_locked(): void
    {
        self::assertSame(['user', 'reg', 'code'], config('viva.mapping_headers'));
    }

    public function test_board_headers_are_locked(): void
    {
        self::assertSame(
            ['viva_date', 'member_id', 'code', 'mark', 'viva_cff', 'viva_em', 'viva_phc', 'invalid', 'issue'],
            config('viva.board_headers'),
        );
        self::assertSame(['viva_date', 'member_id', 'code', 'mark'], config('viva.board_required_headers'));
    }
}
