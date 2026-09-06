<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA1SeatBreakupApportionmentContractTest extends TestCase
{
    #[Test]
    public function generated_seat_breakup_uses_largest_remainder_with_locked_tie_priority(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationSeatBreakupService.php'));
        $config = file_get_contents(config_path('allocation.php'));

        self::assertStringContainsString('Hamilton/largest-remainder apportionment', $service);
        self::assertStringContainsString("intdiv(\$numerator, 100)", $service);
        self::assertStringContainsString("\$numerator % 100", $service);
        self::assertStringContainsString("'mq' => 0, 'cff' => 1, 'em' => 2, 'phc' => 2", $service);
        self::assertStringContainsString("\$remaining = \$total - array_sum(\$seats)", $service);
        self::assertStringNotContainsString("floor(\$total * ((int)\$settings->cff_percent / 100)", $service);

        self::assertStringContainsString('largest-remainder', $config);
        self::assertStringContainsString('MQ -> CFF -> EM/PHC', $config);

        // Locked examples under the default 93/5/1/1 percentages.
        self::assertSame([13, 1, 0, 0], $this->apportion(14));
        self::assertSame([140, 8, 1, 1], $this->apportion(150));

        // Existing 1-9 post special rule remains unchanged.
        self::assertSame([9, 0, 0, 0], $this->apportion(9));
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function apportion(int $total): array
    {
        if ($total < 10) return [$total, 0, 0, 0];

        $percentages = ['mq' => 93, 'cff' => 5, 'em' => 1, 'phc' => 1];
        $bucketOrder = ['mq', 'cff', 'em', 'phc'];
        $tiePriority = ['mq' => 0, 'cff' => 1, 'em' => 2, 'phc' => 2];
        $stableOrder = array_flip($bucketOrder);
        $seats = [];
        $remainders = [];

        foreach ($bucketOrder as $bucket) {
            $numerator = $total * $percentages[$bucket];
            $seats[$bucket] = intdiv($numerator, 100);
            $remainders[$bucket] = $numerator % 100;
        }

        $remaining = $total - array_sum($seats);
        $ranked = $bucketOrder;
        usort($ranked, function (string $a, string $b) use ($remainders, $tiePriority, $stableOrder): int {
            return ($remainders[$b] <=> $remainders[$a])
                ?: ($tiePriority[$a] <=> $tiePriority[$b])
                ?: ($stableOrder[$a] <=> $stableOrder[$b]);
        });

        for ($i = 0; $i < $remaining; $i++) $seats[$ranked[$i]]++;

        return [$seats['mq'], $seats['cff'], $seats['em'], $seats['phc']];
    }
}
