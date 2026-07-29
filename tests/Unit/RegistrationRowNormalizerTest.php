<?php
namespace Tests\Unit;
use App\Services\Registrations\RegistrationRowNormalizer;
use PHPUnit\Framework\TestCase;
/** Protect quota derivation and nullable Bengali fields during imports. */
final class RegistrationRowNormalizerTest extends TestCase
{
 public function test_quota_flag_is_derived_from_raw_source_codes(): void{$row=(new RegistrationRowNormalizer())->normalize(['user'=>'U1','reg'=>'1','name'=>'A','cadre_category'=>1,'has_ff_quota'=>2,'name_bn'=>''],9);$this->assertTrue($row['has_quota']);$this->assertSame(2,$row['has_ff_quota']);$this->assertNull($row['name_bn']);}
 public function test_non_qualifying_ff_code_without_other_quota_is_not_quota(): void{$row=(new RegistrationRowNormalizer())->normalize(['user'=>'U1','reg'=>'1','name'=>'A','cadre_category'=>1,'has_ff_quota'=>1],9);$this->assertFalse($row['has_quota']);}
}
