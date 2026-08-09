<?php
namespace App\Actions\CadreMasters;
use App\Data\CadreMasterData; use App\Models\CadreMaster; use App\Models\User; use App\Services\MasterData\MasterDataAuditService; use Illuminate\Support\Facades\DB;
final class UpdateCadreMasterAction
{
    public function __construct(private readonly MasterDataAuditService $audit) {}
    public function execute(CadreMaster $record,CadreMasterData $data,User $actor,string $reason): CadreMaster
    {
        return DB::transaction(function()use($record,$data,$actor,$reason){
            $before=$record->toArray();
            $record->update($data->toArray()); $record->refresh();
            if($before!==$record->toArray()) $this->audit->record('CADRE_MASTER_UPDATED',$record,$actor,$reason,$before,$record->toArray());
            return $record;
        });
    }
}
