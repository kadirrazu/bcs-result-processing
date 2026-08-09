<?php
namespace App\Actions\CadreSubMasters;
use App\Data\CadreSubMasterData; use App\Models\CadreSubMaster; use App\Models\User; use App\Services\MasterData\MasterDataAuditService; use Illuminate\Support\Facades\DB;
final class UpdateCadreSubMasterAction
{
 public function __construct(private readonly MasterDataAuditService $audit){}
 public function execute(CadreSubMaster $r,CadreSubMasterData $data,User $actor,string $reason):CadreSubMaster{return DB::transaction(function()use($r,$data,$actor,$reason){$before=$r->toArray();$r->update($data->toArray());$r->refresh();if($before!==$r->toArray())$this->audit->record('SUB_CADRE_MASTER_UPDATED',$r,$actor,$reason,$before,$r->toArray());return $r;});}
}
