<?php
namespace App\Actions\CadreMasters;
use App\Data\CadreMasterData; use App\Models\CadreMaster; use App\Models\User; use App\Services\MasterData\MasterDataAuditService; use Illuminate\Support\Facades\DB;
final class CreateCadreMasterAction
{
    public function __construct(private readonly MasterDataAuditService $audit) {}
    public function execute(CadreMasterData $data, User $actor): CadreMaster
    {
        return DB::transaction(function()use($data,$actor){
            $record=CadreMaster::query()->create($data->toArray());
            $this->audit->record('CADRE_MASTER_CREATED',$record,$actor,'Manual master creation.',null,$record->fresh()->toArray());
            return $record;
        });
    }
}
