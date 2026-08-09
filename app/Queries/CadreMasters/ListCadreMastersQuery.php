<?php
namespace App\Queries\CadreMasters;
use App\Models\CadreMaster; use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class ListCadreMastersQuery
{
    public function execute(string $search, int $perPage = 25): LengthAwarePaginator
    {
        $perPage=in_array($perPage,[25,50,100],true)?$perPage:25;
        return CadreMaster::query()->when($search!=='',fn($q)=>$q->where(function($q)use($search){
            $q->where('cadre_code','like',"%{$search}%")
              ->orWhere('cadre_abbr','like',"%{$search}%")
              ->orWhere('cadre_name','like',"%{$search}%")
              ->orWhere('cadre_name_bn','like',"%{$search}%")
              ->orWhere('post_name','like',"%{$search}%")
              ->orWhere('post_name_bn','like',"%{$search}%");
        }))->withCount('subCadres')->orderBy('display_order')->orderBy('cadre_code')->paginate($perPage)->withQueryString();
    }
}
