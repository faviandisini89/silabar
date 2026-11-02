<?php

namespace App\Domain;

final class Csv
{
    /** @return list<list<string>> */
    public static function parse(string $text): array
    {
        $text = str_replace("\r", '', $text);
        $rows = [];
        $cur = [];
        $f = '';
        $q = false;
        $n = strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $c = $text[$i];
            if ($q) {
                if ($c === '"' && ($text[$i + 1] ?? '') === '"') {
                    $f .= '"';
                    $i++;
                } elseif ($c === '"') {
                    $q = false;
                } else {
                    $f .= $c;
                }
            } elseif ($c === '"') {
                $q = true;
            } elseif ($c === ',') {
                $cur[] = $f;
                $f = '';
            } elseif ($c === "\n") {
                $cur[] = $f;
                $rows[] = $cur;
                $cur = [];
                $f = '';
            } else {
                $f .= $c;
            }
        }
        if ($f !== '' || $cur !== []) {
            $cur[] = $f;
            $rows[] = $cur;
        }

        return array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($x) => trim($x) !== '')) > 0));
    }

    /** @param list<list<scalar|null>> $rows */
    public static function build(array $rows): string
    {
        return implode("\n", array_map(function ($r) {
            return implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) ($v ?? '')).'"', $r));
        }, $rows));
    }
}
