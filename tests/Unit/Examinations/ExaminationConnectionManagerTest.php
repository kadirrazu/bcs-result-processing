<?php

namespace Tests\Unit\Examinations;

use App\Models\Examination;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ExaminationConnectionManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_runtime_configuration_without_mutating_the_central_database(): void
    {
        $examination = Examination::factory()->make(['database_name' => 'bcs_exam_47']);
        $centralDatabase = config('database.connections.'.config('database.default').'.database');

        $configuration = app(ExaminationConnectionManager::class)->buildConfiguration($examination);

        $this->assertNotSame($centralDatabase, $configuration['database']);
        $this->assertSame($centralDatabase, config('database.connections.'.config('database.default').'.database'));
    }

    public function test_it_rejects_an_unsafe_database_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ExaminationConnectionManager::class)->buildConfiguration(
            Examination::factory()->make(['database_name' => '../central'])
        );
    }
}
