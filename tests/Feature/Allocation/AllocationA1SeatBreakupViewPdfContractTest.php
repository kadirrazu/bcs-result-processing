<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

final class AllocationA1SeatBreakupViewPdfContractTest extends TestCase
{
    public function test_routes_expose_uploaded_data_view_and_pdf(): void
    {
        $source = file_get_contents(base_path('routes/allocation.php'));
        $this->assertStringContainsString("showSeatBreakup", $source);
        $this->assertStringContainsString("seatBreakupPdf", $source);
        $this->assertStringContainsString("seat-breakup.show", $source);
        $this->assertStringContainsString("seat-breakup.pdf", $source);
    }

    public function test_landing_has_view_and_pdf_actions_before_and_after_finalization(): void
    {
        $source = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $this->assertStringContainsString('View Data', $source);
        $this->assertStringContainsString("route('allocation.seat-breakup.pdf'", $source);
        $this->assertStringContainsString('Finalize / Freeze', $source);
    }

    public function test_pdf_contract_contains_required_header_and_columns(): void
    {
        $source = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));
        foreach ([
            'Exam Title', 'Report Name', 'Generation Date &amp; Time',
            'sl', 'cadre_title / sub_cadre_title', 'cadre_code', 'sub_cadre_code',
            'total_post', 'mq_post', 'cff_post', 'em_post', 'phc_post', 'remarks',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    public function test_row_model_keeps_authoritative_circular_entry_relation(): void
    {
        $source = file_get_contents(app_path('Models/AllocationSeatBreakupRow.php'));
        $this->assertStringContainsString('function circularEntry(): BelongsTo', $source);
        $this->assertStringContainsString("belongsTo(CircularEntry::class, 'circular_entry_id')", $source);
    }
}
