<?php

namespace App\Models;

use App\Enums\CadreCategory;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationValidationStatus;
use Illuminate\Database\Eloquent\Builder;

/** Candidate identity and registration facts stored in the active examination database. */
final class Registration extends ExaminationModel
{
    protected $fillable = [
        'user_id', 'reg', 'national_id', 'name', 'father_name', 'mother_name',
        'name_bn', 'father_name_bn', 'mother_name_bn', 'birth_date', 'ssc_roll', 'ssc_year',
        'hsc_roll', 'hsc_year', 'graduation_year', 'sex_code',
        'district_code', 'division_code', 'university_code', 'bachelor_subject_code',
        'post_related_subject_code', 'cadre_category', 'has_ff_quota', 'has_em_quota',
        'has_phc_quota', 'has_quota', 'status', 'validation_status', 'comment',
        'source_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'ssc_year' => 'integer',
            'hsc_year' => 'integer',
            'graduation_year' => 'integer',
            'cadre_category' => CadreCategory::class,
            'has_quota' => 'boolean',
            'status' => RegistrationStatus::class,
            'validation_status' => RegistrationValidationStatus::class,
        ];
    }

    /** Apply only server-side filters that are useful for operational registration review. */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    // Exact identity matches use indexes; name search remains a controlled fallback.
                    $nested->where('reg', $search)
                        ->orWhere('user_id', $search)
                        ->orWhere('national_id', $search)
                        ->orWhere('ssc_roll', $search)
                        ->orWhere('hsc_roll', $search)
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['cadre_category'] ?? null, fn (Builder $q, mixed $v) => $q->where('cadre_category', (int) $v))
            ->when(array_key_exists('has_quota', $filters) && $filters['has_quota'] !== '', fn (Builder $q) => $q->where('has_quota', (bool) (int) $filters['has_quota']))
            ->when($filters['status'] ?? null, fn (Builder $q, mixed $v) => $q->where('status', $v))
            ->when($filters['sex_code'] ?? null, fn (Builder $q, mixed $v) => $q->where('sex_code', $v))
            ->when($filters['district_code'] ?? null, fn (Builder $q, mixed $v) => $q->where('district_code', $v))
            ->when($filters['division_code'] ?? null, fn (Builder $q, mixed $v) => $q->where('division_code', $v))
            ->when($filters['bachelor_subject_code'] ?? null, fn (Builder $q, mixed $v) => $q->where('bachelor_subject_code', $v));
    }
}
