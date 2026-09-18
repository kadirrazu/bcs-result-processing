<?php
namespace App\Services\NonCadre\Circular;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class NonCadreCircularDatasetService
{
    public function version(int $id): object
    {
        $v=DB::connection('exam')->table('non_cadre_circular_versions')->find($id);
        if (!$v || $v->status !== 'finalized') throw ValidationException::withMessages(['circular'=>'Only a finalized Non-Cadre Circular can be exported.']);
        return $v;
    }
    public function posts(int $id): Collection
    {
        return DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$id)
            ->orderByRaw('post_grade IS NULL ASC')->orderBy('post_grade')->orderBy('post_serial')
            ->orderByRaw('post_sub_serial IS NULL DESC')->orderBy('post_sub_serial')->get();
    }
}