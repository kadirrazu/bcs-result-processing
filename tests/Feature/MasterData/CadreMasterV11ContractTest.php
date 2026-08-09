<?php
namespace Tests\Feature\MasterData;
use Tests\TestCase;
final class CadreMasterV11ContractTest extends TestCase
{
 public function test_v11_master_contract_is_present():void
 {
  $cadre=file_get_contents(app_path('Models/CadreMaster.php'));$sub=file_get_contents(app_path('Models/CadreSubMaster.php'));$config=config('master-data-imports');
  $this->assertStringContainsString('cadre_name_bn',$cadre);$this->assertStringContainsString('post_name_bn',$cadre);$this->assertStringContainsString('parent_cadre_id',$sub);
  $this->assertSame(['cadre_code','cadre_abbr','cadre_name','cadre_name_bn','post_name','post_name_bn','cadre_type','display_order','is_active'],$config['cadre-masters']['headers']);
  $this->assertSame(['parent_cadre_code','sub_cadre_code','sub_cadre_abbr','post_name','post_name_bn','display_order','is_active'],$config['cadre-sub-masters']['headers']);
 }
}
