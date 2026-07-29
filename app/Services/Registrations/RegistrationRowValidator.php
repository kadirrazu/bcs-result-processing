<?php
namespace App\Services\Registrations;
/** Validate required identities and central master codes before a batch reaches the examination table. */
final class RegistrationRowValidator
{
 public function validate(array $r,array $m): array{$e=[];
  if(!preg_match('/^[A-Za-z0-9]{1,10}$/',$r['user_id']))$e[]='USER must be alphanumeric and at most 10 characters.';
  if(!preg_match('/^\d{1,8}$/',$r['reg']))$e[]='REG must contain at most 8 digits.';
  if($r['name']==='')$e[]='NAME is required.';if(!in_array($r['cadre_category'],[1,2,3],true))$e[]='CADRE_CATEGORY must be 1, 2 or 3.';
  foreach(['sex_code'=>'sex','district_code'=>'district','division_code'=>'division','university_code'=>'university','bachelor_subject_code'=>'b_subject','related_subject_code'=>'rl_subject'] as $field=>$map){if($r[$field]!==null&&!isset($m[$map][(string)$r[$field]]))$e[]="Unknown {$map} code [{$r[$field]}].";}
  return $e;}
}
