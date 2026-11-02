<?php

namespace App\Enums;

enum UnitCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Fair => 'Cukup',
            self::Damaged => 'Rusak',
        };
    }
}
