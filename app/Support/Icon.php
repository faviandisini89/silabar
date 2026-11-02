<?php

namespace App\Support;

class Icon
{
    private const PATHS = [
        'home' => 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'ticket' => 'M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4zM14 6v12',
        'clock' => 'M12 7v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z',
        'check' => 'M5 12l5 5 9-10',
        'inbox' => 'M4 13l2-8h12l2 8v6H4zM4 13h5l1 2h4l1-2h5',
        'scan' => 'M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M4 12h16',
        'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
        'wrench' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z',
        'coin' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM9 10c0-1.700 1.300-2 3-2s3 .6 3 2-1.300 1.600-3 2-3 .6-3 2 1.300 2 3 2 3-.3 3-2',
        'chart' => 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-8',
        'trend' => 'M3 17l6-6 4 4 8-8M15 7h6v6',
        'users' => 'M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20c0-3 3-5 6-5s6 2 6 5M14 15c3 0 8 1 8 5',
        'gear' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 12h2M18 12h2M12 4v2M12 18v2M6.300 6.300l1.400 1.400M16.300 16.300l1.400 1.400M6.300 17.700l1.400-1.400M16.300 7.700l1.400-1.400',
        'scroll' => 'M6 3h12v15a3 3 0 0 1-3 3H6zM6 3a3 3 0 0 0-3 3v1h3M9 8h6M9 12h6M9 16h3',
        'bell' => 'M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7M10 20a2 2 0 0 0 4 0',
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M5 12h14',
        'x' => 'M6 6l12 12M18 6L6 18',
        'cal' => 'M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14H4zM4 10h16M8 3v3M16 3v3',
        'alert' => 'M12 4l9 16H3zM12 10v4M12 17h.01',
        'dl' => 'M12 4v11M7 11l5 5 5-5M5 20h14',
        'up' => 'M12 20V9M7 13l5-5 5 5M5 4h14',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 4-6 8-6s8 2 8 6',
        'logout' => 'M10 4H5v16h5M15 8l5 4-5 4M20 12H9',
        'printer' => 'M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z',
        'qr' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM20 14v6h-3M14 20h3',
        'info' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v6M12 7.500h.01',
        'moon' => 'M20 14A8 8 0 0 1 10 4a8 8 0 1 0 10 10z',
        'sun' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.400 1.400M17.600 17.600L19 19M5 19l1.400-1.400M17.600 6.400L19 5',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'trash' => 'M5 7h14M9 7V4h6v3M7 7l1 13h8l1-13',
        'edit' => 'M4 20l1-4L16 5l3 3L8 19zM14 7l3 3',
        'refresh' => 'M20 11a8 8 0 0 0-14-4M4 4v4h4M4 13a8 8 0 0 0 14 4M20 20v-4h-4',
    ];

    public static function svg(string $name, int $size = 18): string
    {
        $d = self::PATHS[$name] ?? '';

        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$d.'"/></svg>';
    }

    public static function logo(int $size = 30): string
    {
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 32 32" aria-hidden="true"><path d="M4 9l12-6 12 6v14l-12 6-12-6z" fill="#F2B544"/><path d="M4 9l12 6 12-6M16 15v14" fill="none" stroke="#12263A" stroke-width="2" stroke-linejoin="round"/><circle cx="16" cy="9.5" r="2" fill="#12263A"/></svg>';
    }
}
