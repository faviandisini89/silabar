<?php

namespace App\Domain;

final class LoanRules
{
    public static function needsApproval(bool $anyItemRequiresApproval, bool $autoApproveEnabled, int $trust, int $threshold): bool
    {
        return $anyItemRequiresApproval && ! ($autoApproveEnabled && $trust >= $threshold);
    }

    public static function days(int $startTs, int $endTs): int
    {
        return (int) ceil(($endTs - $startTs) / 86400);
    }
}
