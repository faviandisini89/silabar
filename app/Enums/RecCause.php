<?php

namespace App\Enums;

enum RecCause: string
{
    case StockShortage = 'stock_shortage';
    case Maintenance = 'maintenance';
    case LateReturns = 'late_returns';
    case PeakScheduling = 'peak_scheduling';

    public function label(): string
    {
        return match ($this) {
            self::StockShortage => 'Stok memang kurang',
            self::Maintenance => 'Banyak unit rusak',
            self::LateReturns => 'Pengembalian sering telat',
            self::PeakScheduling => 'Permintaan menumpuk di jam tertentu',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::StockShortage => 'brick',
            self::Maintenance => 'sun',
            self::LateReturns => 'sky',
            self::PeakScheduling => 'moss',
        };
    }
}
