<?php

namespace App\Enums;

enum PenaltyStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum lunas',
            self::Paid => 'Lunas',
            self::Waived => 'Dihapuskan',
        };
    }
}
