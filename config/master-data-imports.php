<?php

use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\PostRelatedSubject;

return [
    'cadre-masters' => [
        'label' => 'Cadre Masters',
        'model' => CadreMaster::class,
        'route' => 'cadre-masters.index',
        'unique_by' => 'cadre_code',
        'additional_unique_by' => ['cadre_abbr'],
        'headers' => ['cadre_code', 'cadre_abbr', 'cadre_title', 'cadre_title_bn', 'cadre_type', 'display_order', 'is_active'],
        'required' => ['cadre_code', 'cadre_abbr', 'cadre_title', 'cadre_title_bn', 'cadre_type'],
        'sample' => [110, 'ADMN', 'BCS (Administration)', 'বিসিএস (প্রশাসন)', 'GG', 10, 1],
    ],
    'bachelor-subjects' => [
        'label' => 'Bachelor Subjects',
        'model' => BachelorSubject::class,
        'route' => 'bachelor-subjects.index',
        'unique_by' => 'subject_code',
        'additional_unique_by' => [],
        'headers' => ['subject_code', 'subject_name', 'is_active'],
        'required' => ['subject_code', 'subject_name'],
        'sample' => ['001', 'Bangla', 1],
    ],
    'post-related-subjects' => [
        'label' => 'Post Related Subjects',
        'model' => PostRelatedSubject::class,
        'route' => 'post-related-subjects.index',
        'unique_by' => 'subject_code',
        'additional_unique_by' => [],
        'headers' => ['subject_code', 'subject_name', 'is_active'],
        'required' => ['subject_code', 'subject_name'],
        'sample' => ['MEDI', 'Medical Science', 1],
    ],
];
