<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Central master record for a candidate's bachelor or equivalent subject. */
final class BachelorSubject extends Model
{
    use HasFactory;

    protected $fillable = ['subject_code', 'subject_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
