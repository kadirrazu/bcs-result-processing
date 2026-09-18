<?php
namespace Tests\Feature\NonCadre;
use PHPUnit\Framework\Attributes\Test; use Tests\TestCase;
final class NonCadreCircularFinalizedPresentationContractTest extends TestCase
{
 #[Test] public function finalized_circular_has_grade_grouping_exports_and_immutable_presentation():void
 {
  $view=file_get_contents(resource_path('views/non-cadre/circular/version.blade.php')); $routes=file_get_contents(base_path('routes/non-cadre.php')); $controller=file_get_contents(app_path('Http/Controllers/NonCadre/Circular/NonCadreCircularController.php'));
  self::assertStringContainsString('Grade-wise Post Count',$view); self::assertStringContainsString('TOTAL POST COUNT — GRADE',$view); self::assertStringContainsString('rowspan=',$view); self::assertStringContainsString("str_replace('|', ', '",$view); self::assertStringContainsString('version.pdf',$routes); self::assertStringContainsString('version.excel',$routes); self::assertStringNotContainsString('edit',strtolower($routes)); self::assertStringContainsString("->orderBy('post_grade')",$controller); self::assertStringContainsString("->orderBy('post_serial')",$controller); self::assertStringContainsString("->orderBy('post_sub_serial')",$controller);
 }
}