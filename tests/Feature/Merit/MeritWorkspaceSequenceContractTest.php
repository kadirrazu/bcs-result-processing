<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritWorkspaceSequenceContractTest extends TestCase
{
 public function test_workspace_orders_tabulation_circular_merit_before_choice_validation():void{$nav=file_get_contents(config_path('navigation.php'));$tab=strpos($nav,"'label' => 'Tabulation'");$circular=strpos($nav,"'label' => 'Circular'");$merit=strpos($nav,"'label' => 'Merit'");$choice=strpos($nav,"'label' => 'Choice Validation'");$this->assertNotFalse($tab);$this->assertNotFalse($circular);$this->assertNotFalse($merit);$this->assertNotFalse($choice);$this->assertTrue($tab<$circular&&$circular<$merit&&$merit<$choice);}
}
