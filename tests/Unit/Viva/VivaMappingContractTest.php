<?php
namespace Tests\Unit\Viva;
use PHPUnit\Framework\Attributes\Test;use Tests\TestCase;
final class VivaMappingContractTest extends TestCase
{
 #[Test] public function mapping_headers_are_locked():void{$this->assertSame(['user','reg','code'],array_values(config('viva.mapping_headers')));}
 #[Test] public function mapping_chunk_defaults_are_positive():void{$this->assertGreaterThan(0,(int)config('viva.mapping_staging_chunk_size'));$this->assertGreaterThan(0,(int)config('viva.mapping_validation_chunk_size'));}
}
