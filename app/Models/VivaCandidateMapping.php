<?php

namespace App\Models;

final class VivaCandidateMapping extends ExaminationModel
{
    protected $fillable = [
        'registration_id', 'written_result_id', 'user_id', 'reg', 'code', 'source_batch_id',
    ];
}
