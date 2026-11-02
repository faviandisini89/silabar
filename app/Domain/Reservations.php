<?php

namespace App\Domain;

/** Pure stock-window maths: how much of an item is held concurrently inside a time window. */
final class Reservations
{
    /**
     * @param  list<array{start:int,end:int,qty:int}>  $reservations  unix timestamps
     */
    public static function maxConcurrent(array $reservations, int $start, int $end): int
    {
        $events = [];
        foreach ($reservations as $r) {
            if ($r['end'] <= $start || $r['start'] >= $end) {
                continue;
            }
            $events[] = [max($r['start'], $start), $r['qty']];
            $events[] = [min($r['end'], $end), -$r['qty']];
        }
        // at equal timestamps, releases (negative) are applied before new holds
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $cur = 0;
        $max = 0;
        foreach ($events as [, $q]) {
            $cur += $q;
            if ($q > 0 && $cur > $max) {
                $max = $cur;
            }
        }

        return $max;
    }

    /** Calendar colouring: free / mid (50%+ held) / full. */
    public static function loadClass(int $usable, int $reserved): string
    {
        if ($usable <= 0) {
            return 'full';
        }
        $r = $reserved / $usable;

        return $r >= 1 ? 'full' : ($r >= 0.5 ? 'mid' : 'free');
    }
}
