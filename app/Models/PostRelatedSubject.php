<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Central master record for a post-related written examination subject. */
final class PostRelatedSubject extends Model
{
    use HasFactory;

    protected $fillable = ['subject_code', 'subject_name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
