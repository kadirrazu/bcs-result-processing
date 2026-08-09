<?php
namespace App\Queries\CadreSubMasters;
use App\Models\CadreSubMaster; use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class ListCadreSubMastersQuery
{
    public function execute(string $search, int $perPage=25): LengthAwarePaginator
    {
        $perPage=in_array($perPage,[25,50,100],true)?$perPage:25;
        return CadreSubMaster::query()->with('parentCadre')->when($search!=='',fn($q)=>$q->where(function($q)use($search){
            $q->where('sub_cadre_code','like',"%{$search}%")
              ->orWhere('sub_cadre_abbr','like',"%{$search}%")
              ->orWhere('post_name','like',"%{$search}%")
              ->orWhere('post_name_bn','like',"%{$search}%")
              ->orWhereHas('parentCadre',fn($p)=>$p->where('cadre_code','like',"%{$search}%")->orWhere('cadre_name','like',"%{$search}%"));
        }))->orderBy('parent_cadre_id')->orderBy('display_order')->orderBy('sub_cadre_code')->paginate($perPage)->withQueryString();
    }
}
