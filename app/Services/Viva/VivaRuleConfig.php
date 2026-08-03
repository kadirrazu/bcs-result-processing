<?php

namespace App\Services\Viva;

use InvalidArgumentException;

/** Central accessor for the locked, configurable Viva mark rules. */
final class VivaRuleConfig
{
    public function fullMark(): float
    {
        return $this->positive('viva.full_mark');
    }

    public function passPercent(): float
    {
        return $this->percentage('viva.pass_percent');
    }

    public function passMark(): float
    {
        return round($this->fullMark() * $this->passPercent() / 100, 2);
    }

    public function highMarkReviewPercent(): float
    {
        return $this->percentage('viva.high_mark_review_percent');
    }

    public function highMarkReviewMark(): float
    {
        return round($this->fullMark() * $this->highMarkReviewPercent() / 100, 2);
    }

    private function positive(string $key): float
    {
        $value = (float) config($key);
        if ($value <= 0) {
            throw new InvalidArgumentException("{$key} must be greater than zero.");
        }

        return $value;
    }

    private function percentage(string $key): float
    {
        $value = (float) config($key);
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException("{$key} must be between 0 and 100.");
        }

        return $value;
    }
}
