<?php
namespace Tests\Feature\Circular;
use Tests\TestCase;
final class CircularFoundationContractTest extends TestCase
{
 public function test_circular_import_contract_and_foundation_are_locked():void
 {
  $this->assertSame(['cadre_serial','sub_serial','cadre_code','sub_cadre_code','cadre_type','post_count','bachelor_subject_codes','prs_codes','status','note'],config('circular.headers'));
  $this->assertSame('|',config('circular.code_delimiter'));
  $this->assertFileExists(database_path('examination-migrations/2026_08_09_230000_create_circular_module_foundation.php'));
  $this->assertStringContainsString("'label' => 'Circular'",file_get_contents(config_path('navigation.php')));
 }
}
