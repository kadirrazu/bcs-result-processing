<?php

namespace App\Services\Tabulation;

final class TabulationSourceSnapshotComparator
{
    public function equivalent(?array $left, ?array $right): bool
    {
        return $this->canonicalize($left ?? []) === $this->canonicalize($right ?? []);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
