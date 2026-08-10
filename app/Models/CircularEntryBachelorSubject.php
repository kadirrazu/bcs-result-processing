<?php

namespace App\Models;

final class CircularEntryBachelorSubject extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'circular_entry_id',
        'subject_code',
    ];
}
