<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Fmt
{
    public static function rp(float|int|null $n): string
    {
        return 'Rp'.number_format((float) $n, 0, ',', '.');
    }

    public static function pct(float|int|null $v, int $d = 0): string
    {
        return number_format(((float) $v) * 100, $d, ',', '.').'%';
    }

    public static function dt(?Carbon $c): string
    {
        return $c ? $c->translatedFormat('j M, H.i') : '-';
    }

    public static function d(?Carbon $c): string
    {
        return $c ? $c->translatedFormat('j M Y') : '-';
    }

    /** "ambil dalam 2 hari" / "ambil sekarang" */
    public static function pickup(Carbon $start): string
    {
        $diff = now()->diffInMinutes($start, false);
        if ($diff <= 0) {
            return 'ambil sekarang';
        }
        $s = $diff < 60 ? (int) max(1, $diff).' menit' : ($diff < 1440 ? (int) round($diff / 60).' jam' : (int) round($diff / 1440).' hari');

        return "ambil dalam {$s}";
    }

    /** "sisa 5 jam" / "terlambat 2 hari" */
    public static function until(Carbon $due): string
    {
        $diff = now()->diffInMinutes($due, false);
        $a = abs($diff);
        $s = $a < 60 ? max(1, (int) $a).' menit' : ($a < 1440 ? (int) round($a / 60).' jam' : (int) round($a / 1440).' hari');

        return $diff < 0 ? "terlambat {$s}" : "sisa {$s}";
    }
}
