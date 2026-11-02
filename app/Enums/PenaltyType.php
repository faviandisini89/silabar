<?php

namespace App\Enums;

enum PenaltyType: string
{
    case Late = 'late';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Late => 'Terlambat',
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
        };
    }
}
