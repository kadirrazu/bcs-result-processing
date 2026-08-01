<?php

namespace App\Services\Written;

use InvalidArgumentException;

/** Single typed boundary around Written subject/rule configuration. */
final class WrittenSubjectConfig
{
    /** @return array<string,array{full_mark:int|float,display_order:int}> */
    public function subjects(): array
    {
        return (array) config('written.subjects', []);
    }

    /** @return list<string> */
    public function trackSubjects(string $track): array
    {
        $subjects = config("written.tracks.{$track}.subjects");

        if (! is_array($subjects)) {
            throw new InvalidArgumentException("Unknown written track [{$track}].");
        }

        return array_values($subjects);
    }

    public function fullMark(string $subjectCode): float
    {
        $value = config("written.subjects.{$subjectCode}.full_mark");

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Unknown written subject [{$subjectCode}].");
        }

        return (float) $value;
    }

    public function paperCrashThreshold(string $subjectCode): float
    {
        return $this->fullMark($subjectCode) * ((float) config('written.paper_crash_percent', 30) / 100);
    }

    public function trackFullMark(string $track): float
    {
        return array_sum(array_map(fn (string $code): float => $this->fullMark($code), $this->trackSubjects($track)));
    }

    public function trackPassThreshold(string $track): float
    {
        return $this->trackFullMark($track) * ((float) config('written.written_pass_percent', 50) / 100);
    }

    public function highMarkThreshold(string $subjectCode): float
    {
        return $this->fullMark($subjectCode) * ((float) config('written.high_mark_review_percent', 75) / 100);
    }

    /** @return list<string> */
    public function combined008009(): array
    {
        return array_values((array) config('written.combined_groups.008_009.subjects', ['008', '009']));
    }
}
