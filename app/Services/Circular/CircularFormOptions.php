<?php

namespace App\Services\Circular;

use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\PostRelatedSubject;

final class CircularFormOptions
{
    public function get(): array
    {
        return [
            'cadres' => CadreMaster::query()->where('is_active', true)->orderBy('display_order')->orderBy('cadre_code')->get(),
            'subCadres' => CadreSubMaster::query()->with('parentCadre')->where('is_active', true)->orderBy('display_order')->orderBy('sub_cadre_code')->get(),
            'bachelorSubjects' => BachelorSubject::query()->where('is_active', true)->orderBy('subject_code')->get(),
            'prsSubjects' => PostRelatedSubject::query()->where('is_active', true)->orderBy('subject_code')->get(),
        ];
    }
}
