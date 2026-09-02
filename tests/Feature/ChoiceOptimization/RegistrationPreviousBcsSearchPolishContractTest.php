<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\TestCase;

class RegistrationPreviousBcsSearchPolishContractTest extends TestCase
{
    public function test_registration_candidate_shows_smaller_labeled_father_name(): void
    {
        $view = file_get_contents(resource_path('views/registrations/index.blade.php'));

        $this->assertStringContainsString('Father: {{ $record->father_name', $view);
        $this->assertStringContainsString('font-size:.72rem', $view);
    }

    public function test_consolidated_previous_bcs_table_matches_dataset_review_style(): void
    {
        $view = file_get_contents(resource_path('views/previous-bcs-repository/search.blade.php'));

        $this->assertStringContainsString('<th>Reg / Name</th>', $view);
        $this->assertStringContainsString('<th>Primary DOB</th>', $view);
        $this->assertStringContainsString('<th>Secondary DOB</th>', $view);
        $this->assertStringContainsString('Roll:</span> <code>{{ $row->ssc_roll', $view);
        $this->assertStringContainsString('Year:</span> {{ $row->ssc_year', $view);
        $this->assertStringContainsString('Roll:</span> <code>{{ $row->hsc_roll', $view);
        $this->assertStringContainsString('Year:</span> {{ $row->hsc_year', $view);

        $this->assertStringNotContainsString('<th>Mother</th>', $view);
        $this->assertStringNotContainsString('<th>NID</th>', $view);
    }
}
