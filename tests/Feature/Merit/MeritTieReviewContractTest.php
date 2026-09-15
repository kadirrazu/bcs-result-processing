<?php
namespace Tests\Feature\Merit;
use Tests\TestCase;
final class MeritTieReviewContractTest extends TestCase
{
 public function test_tie_review_is_non_blocking_for_common_general_technical_only():void{$ranking=file_get_contents(app_path('Services/Merit/MeritRankingService.php'));$service=file_get_contents(app_path('Services/Merit/MeritTieReviewService.php'));$view=file_get_contents(resource_path('views/merit/results.blade.php'));$routes=file_get_contents(base_path('routes/merit.php'));$this->assertStringContainsString('businessTieGroups',$ranking);foreach(["'common' => 'Common Merit'","'general' => 'General Merit'","'technical' => 'Technical Merit'"] as $scope)$this->assertStringContainsString($scope,$service);$this->assertStringNotContainsString('cadre_merit',$service);$this->assertStringContainsString('Non-blocking Commission review',$view);$this->assertStringContainsString('tie-review.pdf',$routes);$this->assertStringContainsString('tie-review.xlsx',$routes);}
}
