<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularC3ContractTest extends TestCase
{
    public function test_manual_correction_delete_and_stale_contract_is_present(): void
    {
        $storeRequest = file_get_contents(app_path('Http/Requests/StoreCircularEntryRequest.php'));
        $this->assertStringContainsString("'correction_reason' => ['required'", $storeRequest);

        $service = file_get_contents(app_path('Services/Circular/CircularDatasetService.php'));
        $this->assertStringContainsString('hasMeaningfulChange', $service);
        $this->assertStringContainsString('A no-op must not fork a version', $service);
        $this->assertStringContainsString('DOWNSTREAM_STAGES', $service);
        $this->assertStringContainsString("'choice_validation' => 'Choice Validation'", $service);
        $this->assertStringContainsString("'allocation_preparation' => 'Allocation Preparation / Allocation'", $service);
        $this->assertStringContainsString('circular_entry_deleted', $service);

        $edit = file_get_contents(resource_path('views/circular/edit.blade.php'));
        $this->assertStringContainsString('Reason for deletion', $edit);
        $this->assertStringContainsString("route('circular.entries.destroy'", $edit);

        $index = file_get_contents(resource_path('views/circular/index.blade.php'));
        $this->assertStringContainsString('Downstream Dependency Status', $index);
        $this->assertStringContainsString('Registration, Preliminary, Written or Viva', $index);
    }
}
