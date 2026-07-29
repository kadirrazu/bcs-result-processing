<?php

namespace App\Services\Registrations;

use App\Models\BachelorSubject;
use App\Models\District;
use App\Models\Division;
use App\Models\Gender;
use App\Models\PostRelatedSubject;
use App\Models\University;

/**
 * Load central master options once per form request.
 *
 * Registration rows store stable codes rather than cross-database foreign keys,
 * so these compact lists are the source of truth for manual entry dropdowns.
 */
final class RegistrationFormOptions
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        return [
            'genders' => Gender::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'divisions' => Division::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'districts' => District::query()->where('is_active', true)->orderBy('name')->get(['code', 'division_code', 'name']),
            'universities' => University::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']),
            'bachelorSubjects' => BachelorSubject::query()->where('is_active', true)->orderBy('subject_name')->get(['subject_code', 'subject_name']),
            'relatedSubjects' => PostRelatedSubject::query()->where('is_active', true)->orderBy('subject_name')->get(['subject_code', 'subject_name']),
        ];
    }
}
