<?php

namespace App\Models;

final class CircularEntryPrs extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'circular_entry_id',
        'prs_code',
    ];
}
