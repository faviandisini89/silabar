<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Petugas = 'petugas';
    case Peminjam = 'peminjam';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Petugas => 'Petugas',
            self::Peminjam => 'Peminjam',
        };
    }

    /** Admin inherits every Staff right. */
    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Petugas;
    }

    public function home(): string
    {
        return match ($this) {
            self::Admin => 'admin.analytics',
            self::Petugas => 'staff.agenda',
            self::Peminjam => 'home',
        };
    }
}
