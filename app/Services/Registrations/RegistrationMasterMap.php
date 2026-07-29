<?php
namespace App\Services\Registrations;
use App\Models\{BachelorSubject,District,Division,Gender,PostRelatedSubject,University};
/** Load central master codes once so a 300k-row import never performs per-row SQL lookups. */
final class RegistrationMasterMap
{
 public function load(): array{return [
  'sex'=>Gender::query()->where('is_active',true)->pluck('code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
  'district'=>District::query()->where('is_active',true)->pluck('code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
  'division'=>Division::query()->where('is_active',true)->pluck('code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
  'university'=>University::query()->where('is_active',true)->pluck('code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
  'b_subject'=>BachelorSubject::query()->where('is_active',true)->pluck('subject_code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
  'rl_subject'=>PostRelatedSubject::query()->where('is_active',true)->pluck('subject_code')->mapWithKeys(fn($v)=>[(string)$v=>true])->all(),
 ];}
}
