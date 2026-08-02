<?php

namespace App\Services\MasterData;

use App\Enums\CadreCategory;
use App\Models\BachelorSubject;
use App\Models\District;
use App\Models\Division;
use App\Models\Gender;
use App\Models\PostRelatedSubject;
use App\Models\University;

/**
 * Present stored reference codes consistently as "CODE - Title/ABBR".
 *
 * Operational/examination tables continue to store only stable codes. This
 * service is display-only and resolves their human-readable central-master
 * labels without changing the stored source value.
 */
final class CodeLabelService
{
    public function cadreCategory(mixed $value): string
    {
        if ($value instanceof CadreCategory) {
            return $this->format($value->value, $value->code());
        }

        $code = $this->normalize($value);
        if ($code === null || ! ctype_digit($code)) {
            return $code ?? '—';
        }

        $category = CadreCategory::tryFrom((int) $code);

        return $category
            ? $this->format($category->value, $category->code())
            : $code;
    }

    public function postRelatedSubject(mixed $code): string
    {
        $normalized = $this->normalize($code);
        if ($normalized === null) {
            return '—';
        }

        $title = PostRelatedSubject::query()
            ->where('subject_code', $normalized)
            ->value('subject_name');

        return $this->format($normalized, $title);
    }

    public function bachelorSubject(mixed $code): string
    {
        $normalized = $this->normalize($code);
        if ($normalized === null) {
            return '—';
        }

        $title = BachelorSubject::query()
            ->where('subject_code', $normalized)
            ->value('subject_name');

        return $this->format($normalized, $title);
    }

    public function district(mixed $code): string
    {
        return $this->masterCodeLabel(District::class, $code, 'code', 'name');
    }

    public function division(mixed $code): string
    {
        return $this->masterCodeLabel(Division::class, $code, 'code', 'name');
    }

    public function university(mixed $code): string
    {
        return $this->masterCodeLabel(University::class, $code, 'code', 'name');
    }

    public function gender(mixed $code): string
    {
        return $this->masterCodeLabel(Gender::class, $code, 'code', 'name');
    }

    public function format(mixed $code, mixed $title): string
    {
        $normalizedCode = $this->normalize($code);
        if ($normalizedCode === null) {
            return '—';
        }

        $normalizedTitle = trim((string) ($title ?? ''));

        return $normalizedTitle !== ''
            ? $normalizedCode.' - '.$normalizedTitle
            : $normalizedCode;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function masterCodeLabel(string $model, mixed $code, string $codeColumn, string $titleColumn): string
    {
        $normalized = $this->normalize($code);
        if ($normalized === null) {
            return '—';
        }

        $title = $model::query()->where($codeColumn, $normalized)->value($titleColumn);

        return $this->format($normalized, $title);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
