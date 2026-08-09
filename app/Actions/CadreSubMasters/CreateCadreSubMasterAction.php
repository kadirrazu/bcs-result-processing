<?php
namespace App\Actions\CadreSubMasters;
use App\Data\CadreSubMasterData; use App\Models\CadreSubMaster; use App\Models\User; use App\Services\MasterData\MasterDataAuditService; use Illuminate\Support\Facades\DB;
final class CreateCadreSubMasterAction
{
 public function __construct(private readonly MasterDataAuditService $audit){}
 public function execute(CadreSubMasterData $data,User $actor):CadreSubMaster{return DB::transaction(function()use($data,$actor){$r=CadreSubMaster::query()->create($data->toArray());$this->audit->record('SUB_CADRE_MASTER_CREATED',$r,$actor,'Manual master creation.',null,$r->fresh()->toArray());return $r;});}
}
