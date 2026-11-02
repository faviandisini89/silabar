<?php

namespace App\Domain;

/** Borrower reliability, 10..100, from the most recent returned loans. */
final class TrustScore
{
    /**
     * @param  list<bool>  $recentWasLate  newest first, only returned loans
     */
    public static function compute(array $recentWasLate, int $recentDamageOrLoss = 0): int
    {
        $recent = array_slice($recentWasLate, 0, 12);
        if (count($recent) < 3) {
            return 70;
        }
        $onTime = count(array_filter($recent, fn ($late) => ! $late)) / count($recent);
        $score = (int) round(100 * ($onTime ** 1.6)) - 4 * $recentDamageOrLoss;

        return max(10, min(100, $score));
    }
}
