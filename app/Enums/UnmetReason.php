<?php

namespace App\Enums;

enum UnmetReason: string
{
    case OutOfStock = 'out_of_stock';
    case DateConflict = 'date_conflict';

    public function label(): string
    {
        return match ($this) {
            self::OutOfStock => 'Stok tidak cukup',
            self::DateConflict => 'Jadwal bentrok',
        };
    }
}
