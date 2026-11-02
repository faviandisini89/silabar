<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case Loaned = 'loaned';
    case Maintenance = 'maintenance';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Loaned => 'Dipinjam',
            self::Maintenance => 'Perawatan',
            self::Lost => 'Hilang',
        };
    }
}
