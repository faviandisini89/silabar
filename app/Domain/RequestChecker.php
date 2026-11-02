<?php

namespace App\Domain;

/**
 * The four automatic checks of a loan request (stock, schedule, outstanding penalty, duration).
 * Pure: callers pass in the availability numbers they computed.
 */
final class RequestChecker
{
    /**
     * @param  list<array{item_id:int,name:string,qty:int,max_days:int,usable:int,reserved:int}>  $lines
     * @return array{ok:bool,fails:list<array>,ck:array<string,bool>}
     */
    public static function check(array $lines, int $startTs, int $endTs, float $unpaidAmount): array
    {
        $fails = [];
        $ck = ['range' => true, 'penalty' => true, 'stock' => true, 'conflict' => true, 'duration' => true];

        if ($endTs <= $startTs) {
            $ck['range'] = false;
            $fails[] = ['code' => 'invalid_range', 'msg' => 'Waktu selesai harus setelah waktu mulai.'];
        }
        if ($unpaidAmount > 0) {
            $ck['penalty'] = false;
            $fails[] = ['code' => 'outstanding_penalty', 'msg' => 'Masih ada denda belum lunas Rp'.number_format($unpaidAmount, 0, ',', '.').'. Selesaikan di loket Petugas dulu.'];
        }
        $days = LoanRules::days($startTs, $endTs);
        foreach ($lines as $ln) {
            if ($endTs > $startTs && $days > $ln['max_days']) {
                $ck['duration'] = false;
                $fails[] = ['code' => 'max_duration_exceeded', 'item_id' => $ln['item_id'], 'msg' => "{$ln['name']}: maksimal {$ln['max_days']} hari, kamu meminta {$days} hari."];
            }
            $avail = max(0, $ln['usable'] - $ln['reserved']);
            if ($ln['qty'] > $avail) {
                $reason = ($avail <= 0 && $ln['usable'] > 0) ? 'date_conflict' : 'out_of_stock';
                $ck[$reason === 'date_conflict' ? 'conflict' : 'stock'] = false;
                $fails[] = [
                    'code' => $reason, 'item_id' => $ln['item_id'], 'qty' => $ln['qty'], 'avail' => $avail,
                    'msg' => $reason === 'date_conflict'
                        ? "{$ln['name']}: semua unit sudah dipesan pada rentang ini."
                        : "{$ln['name']}: stok tidak cukup, tersedia {$avail} dari {$ln['qty']} yang diminta.",
                ];
            }
        }

        return ['ok' => $fails === [], 'fails' => $fails, 'ck' => $ck];
    }
}
