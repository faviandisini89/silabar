<?php

namespace App\Support;

/** Server-rendered chart markup (no JS chart library, so dashboards work offline and print cleanly). */
class Charts
{
    private static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** @param list<array{name:string,v:int|float,color?:string}> $rows */
    public static function hbars(array $rows, string $suffix = ''): string
    {
        $max = max(1, ...array_map(fn ($r) => $r['v'], $rows ?: [['v' => 1]]));
        $o = '';
        foreach ($rows as $i => $r) {
            $w = round($r['v'] / $max * 100, 1);
            $o .= '<div class="hb"><div class="n" title="'.self::e($r['name']).'">'.self::e($r['name']).'</div><div class="bar"><i style="width:'.$w.'%;animation-delay:'.($i * 50).'ms;'.(isset($r['color']) ? 'background:'.$r['color'] : '').'"></i></div><div class="v">'.self::e($r['v']).$suffix.'</div></div>';
        }

        return $o;
    }

    /** @param list<array{label:string,v:int,cur:bool}> $series */
    public static function cols(array $series): string
    {
        $max = max(1, ...array_map(fn ($s) => $s['v'], $series ?: [['v' => 1]]));
        $o = '<div class="cols">';
        foreach ($series as $i => $s) {
            $o .= '<div class="cl'.($s['cur'] ? ' cur' : '').'"><b>'.$s['v'].'</b><i style="height:calc((100% - 38px) * '.round($s['v'] / $max, 3).');animation-delay:'.($i * 35).'ms"></i><span>'.self::e($s['label']).'</span></div>';
        }

        return $o.'</div>';
    }

    public static function spark(array $vals, int $w = 76, int $h = 28): string
    {
        $max = max(1, ...$vals);
        $n = max(1, count($vals) - 1);
        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = round($i * $w / $n, 1).','.round($h - 3 - ($v / $max) * ($h - 6), 1);
        }

        return '<svg class="spark" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><polyline points="'.implode(' ', $pts).'" fill="none" stroke="var(--pri)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    public static function gauge(int $v, int $size = 96, ?string $label = null): string
    {
        $r = 38;
        $c = 2 * M_PI * $r;
        $col = $v >= 75 ? 'var(--moss)' : ($v >= 50 ? 'var(--sun)' : 'var(--brick)');

        return '<div class="gauge" style="width:'.$size.'px;height:'.$size.'px"><svg viewBox="0 0 96 96" width="'.$size.'" height="'.$size.'"><circle class="bg" cx="48" cy="48" r="'.$r.'"/><circle class="fg" cx="48" cy="48" r="'.$r.'" stroke="'.$col.'" stroke-dasharray="'.round($c, 2).'" stroke-dashoffset="'.round($c, 2).'" data-off="'.round($c * (1 - $v / 100), 2).'"/></svg><b>'.self::e($label ?? $v).'</b></div>';
    }

    public static function ring(float $ratio, bool $invert = false): string
    {
        $r = max(0, min(1, $ratio));
        $cls = $invert ? ($r > .5 ? 'low' : ($r > .3 ? 'mid' : '')) : ($r < .34 ? 'low' : ($r < .67 ? 'mid' : ''));

        return '<div class="ring"><i class="'.$cls.'" style="width:'.round($r * 100).'%"></i></div>';
    }

    /** @param array{grid:array,max:int} $h */
    public static function heat(array $h): string
    {
        $days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $o = '<div class="heat" style="grid-template-columns:34px repeat(11,minmax(0,1fr))"><span></span>';
        for ($i = 7; $i <= 17; $i++) {
            $o .= '<span class="xs muted" style="text-align:center">'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'</span>';
        }
        foreach ($h['grid'] as $d => $row) {
            $o .= '<span class="hl">'.$days[$d].'</span>';
            foreach ($row as $i => $n) {
                $o .= '<div class="hc" title="'.$days[$d].' '.($i + 7).'.00: '.$n.' peminjaman" style="background:color-mix(in srgb,var(--pri) '.round($n / $h['max'] * 100).'%,var(--panel2))">'.$n.'</div>';
            }
        }

        return $o.'</div>';
    }

    /**
     * @param list<int|float> $hist
     * @param list<array{v:float,lo:float,hi:float}> $fc
     * @param list<string> $labels
     */
    public static function line(array $hist, array $fc, array $labels): string
    {
        $W = 560;
        $H = 190;
        $pl = 30;
        $pr = 10;
        $pt = 12;
        $pb = 24;
        $all = array_merge([1], $hist, array_map(fn ($f) => $f['hi'], $fc));
        $mx = max($all) * 1.1;
        $n = max(2, count($hist) + count($fc));
        $X = fn ($i) => $pl + $i * ($W - $pl - $pr) / ($n - 1);
        $Y = fn ($v) => $pt + (1 - $v / $mx) * ($H - $pt - $pb);
        $g = '';
        for ($k = 0; $k <= 4; $k++) {
            $v = $mx * $k / 4;
            $g .= '<line class="grid" x1="'.$pl.'" x2="'.($W - $pr).'" y1="'.round($Y($v), 1).'" y2="'.round($Y($v), 1).'"/><text x="'.($pl - 6).'" y="'.round($Y($v) + 4, 1).'" text-anchor="end">'.round($v).'</text>';
        }
        $hp = '';
        foreach ($hist as $i => $v) {
            $hp .= ($i ? 'L' : 'M').round($X($i), 1).','.round($Y($v), 1);
        }
        $last = count($hist) - 1;
        $area = $hp.'L'.round($X($last), 1).','.round($Y(0), 1).'L'.round($X(0), 1).','.round($Y(0), 1).'Z';
        $fcPath = '';
        $band = '';
        if ($fc) {
            $pts = array_merge([['v' => end($hist), 'lo' => end($hist), 'hi' => end($hist)]], $fc);
            $up = [];
            $lo = [];
            foreach ($pts as $i => $p) {
                $up[] = round($X($last + $i), 1).','.round($Y($p['hi']), 1);
                $lo[] = round($X($last + $i), 1).','.round($Y($p['lo']), 1);
                $fcPath .= ($i ? 'L' : 'M').round($X($last + $i), 1).','.round($Y($p['v']), 1);
            }
            $band = 'M'.implode('L', $up).'L'.implode('L', array_reverse($lo)).'Z';
        }
        $lab = '';
        foreach ($labels as $i => $t) {
            if ($i % 2 === 0) {
                $lab .= '<text x="'.round($X($i), 1).'" y="'.($H - 6).'" text-anchor="middle">'.self::e($t).'</text>';
            }
        }

        return '<svg class="linechart" viewBox="0 0 '.$W.' '.$H.'" width="100%" role="img" aria-label="Grafik permintaan">'.$g.'<path class="area" d="'.$area.'"/>'.($band ? '<path class="band" d="'.$band.'"/>' : '').'<path class="ln dr" d="'.$hp.'"/>'.($fcPath ? '<path class="fc" d="'.$fcPath.'"/>' : '').'<circle cx="'.round($X($last), 1).'" cy="'.round($Y(end($hist)), 1).'" r="4" fill="var(--pri)"/>'.$lab.'</svg>';
    }
}
