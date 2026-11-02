<?php

namespace App\Domain;

final class PenaltyCalculator
{
    public function __construct(private float $latePerUnitPerDay, private float $damagedPercent)
    {
    }

    public function late(int $lateDays, int $qty): float
    {
        return max(0, $lateDays) * $this->latePerUnitPerDay * max(0, $qty);
    }

    public function damaged(float $replacementValue, int $qty): float
    {
        return round($replacementValue * max(0, $qty) * $this->damagedPercent / 100, 2);
    }

    public function lost(float $replacementValue, int $qty): float
    {
        return round($replacementValue * max(0, $qty), 2);
    }

    public static function lateDays(int $dueTs, int $returnedTs): int
    {
        return $returnedTs > $dueTs ? (int) ceil(($returnedTs - $dueTs) / 86400) : 0;
    }
}
