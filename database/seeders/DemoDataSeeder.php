<?php

namespace Database\Seeders;

use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\LoanExtension;
use App\Models\LoanReturn;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\Penalty;
use App\Models\ReturnDetail;
use App\Models\UnmetDemandLog;
use App\Models\User;
use App\Services\AnalyticsClient;
use App\Services\AnalyticsRunner;
use App\Services\ItemService;
use App\Services\LoanService;
use App\Services\Notifier;
use App\Services\RequestValidator;
use App\Services\StatsService;
use App\Services\TrustScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Realistic synthetic history so every dashboard and the ML models have something to learn from.
 * Requests are replayed in time order through the real validation/loan services, so unmet demand,
 * schedule conflicts and reservations come out of the same rules the live system uses.
 */
class DemoDataSeeder extends Seeder
{
    private const PURPOSES = ['Seminar himpunan', 'Pelatihan anggota baru', 'Dokumentasi acara', 'Rapat panitia', 'Lomba antar kelas', 'Workshop desain', 'Bakti sosial', 'Kuliah umum', 'Gladi resik wisuda', 'Pameran karya mahasiswa', 'Praktikum lapangan', 'Bazar kampus'];

    private const REJECT = ['Barang dipakai kegiatan prioritas fakultas', 'Tujuan peminjaman belum jelas', 'Jadwal bentrok dengan agenda kampus'];

    public function run(
        ItemService $itemSvc, LoanService $loans, RequestValidator $validator, StatsService $stats,
        TrustScoreService $trust, Notifier $notifier, AnalyticsClient $client, AnalyticsRunner $runner,
    ): void {
        mt_srand(20261013);
        Cache::forget('silabar.settings');
        $now = now()->startOfMinute();
        $today = $now->copy()->startOfDay();
        $R = fn () => mt_rand() / mt_getrandmax();
        $ri = fn (int $a, int $b) => mt_rand($a, $b);
        $pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
        $wpick = function (array $items, callable $w) use ($R) {
            $tot = array_sum(array_map($w, $items));
            $r = $R() * $tot;
            foreach ($items as $x) {
                $r -= $w($x);
                if ($r <= 0) {
                    return $x;
                }
            }

            return end($items);
        };

        $this->command?->info('Menyiapkan data dasar...');
        $this->call(BaseSeeder::class);
        $cat = fn (string $n) => Category::where('name', $n)->first()->id;
        $loc = fn (string $n) => Location::where('name', $n)->first()->id;

        /* ---------- items ---------- */
        $catalog = [
            ['PRJ', 'Proyektor Epson EB-X06', 'Audio visual', 'Gudang Sarpras Lt. 1', 'unit', 6, 3, true, 5800000, 0, 'Proyektor 3600 lumen untuk ruang kelas dan aula kecil.'],
            ['LTP', 'Laptop Lenovo ThinkPad E14', 'Elektronik', 'Lab Multimedia', 'unit', 5, 3, true, 9500000, 1, 'Laptop kerja untuk presentasi dan olah data.'],
            ['CAM', 'Kamera Sony A6400', 'Audio visual', 'Lab Multimedia', 'unit', 3, 3, true, 12500000, 2, 'Mirrorless dengan lensa kit 16–50 mm.'],
            ['TRP', 'Tripod Manfrotto', 'Audio visual', 'Lab Multimedia', 'unit', 4, 3, false, 900000, 3, 'Tripod aluminium, tinggi maksimal 160 cm.'],
            ['MIC', 'Mikrofon Wireless Shure', 'Audio visual', 'Gudang Sarpras Lt. 1', 'unit', 4, 3, true, 3200000, 4, 'Satu set dua mikrofon genggam nirkabel.'],
            ['SPK', 'Speaker Portable JBL', 'Audio visual', 'Gudang Sarpras Lt. 1', 'unit', 3, 2, true, 4800000, 5, 'Speaker 100 W dengan baterai dan input mikrofon.'],
            ['SCR', 'Layar Proyektor Portable', 'Audio visual', 'Gudang Sarpras Lt. 1', 'unit', 3, 3, false, 1100000, 6, 'Layar tripod ukuran 70 inci.'],
            ['HDM', 'Kabel HDMI 5 m', 'Kabel dan aksesori', 'Gudang Sarpras Lt. 1', 'bulk', 12, 5, false, 85000, 1, 'Kabel HDMI 4K sepanjang 5 meter.'],
            ['TRM', 'Terminal listrik 5 m', 'Kabel dan aksesori', 'Gudang Sarpras Lt. 1', 'bulk', 20, 5, false, 120000, 3, 'Terminal 4 lubang dengan kabel 5 meter.'],
            ['KRS', 'Kursi lipat', 'Perlengkapan acara', 'Ruang Organisasi', 'bulk', 80, 3, false, 150000, 4, 'Kursi lipat besi, disimpan per tumpuk.'],
            ['MJA', 'Meja lipat', 'Perlengkapan acara', 'Ruang Organisasi', 'bulk', 30, 3, false, 350000, 6, 'Meja lipat ukuran 120 × 60 cm.'],
            ['TND', 'Tenda lipat 3×3 m', 'Perlengkapan acara', 'Ruang Organisasi', 'unit', 4, 3, true, 2200000, 2, 'Tenda lipat dengan dinding samping.'],
            ['WBD', 'Whiteboard portable', 'Perlengkapan acara', 'Ruang Organisasi', 'unit', 4, 5, false, 750000, 5, 'Whiteboard beroda dengan penghapus dan spidol.'],
            ['WKT', 'Walkie talkie (sepasang)', 'Elektronik', 'Gudang Sarpras Lt. 1', 'unit', 8, 3, false, 600000, 0, 'Sepasang walkie talkie jarak 3 km.'],
        ];
        $item = [];
        foreach ($catalog as [$sku, $name, $c, $l, $tr, $n, $md, $ap, $val, $tile, $desc]) {
            $item[$sku] = $itemSvc->createItem([
                'sku' => $sku, 'name' => $name, 'category_id' => $cat($c), 'location_id' => $loc($l), 'tracking_type' => $tr, 'max_loan_days' => $md,
                'requires_approval' => $ap, 'replacement_value' => $val, 'tile' => $tile, 'description' => $desc,
            ], $n);
        }

        /* ---------- people ---------- */
        $pw = config('silabar.demo_password');
        $admin = User::where('email', 'admin@silabar.id')->first();
        $staff = collect([['Agus Santoso', 'agus@silabar.id', 'NIP 198807202014'], ['Dimas Putra', 'dimas@silabar.id', 'NIP 199103152017']])->map(
            fn ($s) => User::create(['name' => $s[0], 'email' => $s[1], 'password' => $pw, 'role' => Role::Petugas->value, 'nim' => $s[2], 'organization' => 'Biro Sarana Prasarana', 'trust_score' => 100, 'is_active' => true])
        );
        $people = [
            ['Rina Maharani', 'rina', '2210101001', 'Himpunan Mahasiswa Informatika', .03, 3], ['Dani Kurniawan', 'dani', '2210101014', 'BEM Fakultas Sains', .55, 2],
            ['Salsa Nabila', 'salsa', '2210101027', 'UKM Fotografi', .08, 2], ['Fikri Ramadhan', 'fikri', '2210101033', 'Panitia Seminar Nasional', .20, 1.5],
            ['Aulia Putri', 'aulia', '2210101042', 'Himpunan Mahasiswa Sistem Informasi', .05, 1.5], ['Bagas Pratama', 'bagas', '2210101058', 'UKM Musik', .45, 2],
            ['Nadia Safitri', 'nadia', '2210101061', 'BEM Fakultas Sains', .06, 1.5], ['Rizky Aditya', 'rizky', '2210101075', 'UKM Teater', .30, 1],
            ['Maya Lestari', 'maya', '2210101083', 'Himpunan Mahasiswa Matematika', .10, 1], ['Yoga Permana', 'yoga', '2210101090', 'Panitia Dies Natalis', .25, 1],
            ['Intan Permata', 'intan', '2210101102', 'UKM Fotografi', .04, 1], ['Hafidz Alamsyah', 'hafidz', '2210101117', 'Komunitas Pecinta Alam', .40, 1],
        ];
        $borrowers = [];
        $lateProb = [];
        foreach ($people as [$name, $slug, $nim, $org, $late, $w]) {
            $u = User::create(['name' => $name, 'email' => $slug.'@mhs.silabar.id', 'password' => $pw, 'role' => Role::Peminjam->value, 'nim' => $nim, 'organization' => $org, 'trust_score' => 70, 'is_active' => true]);
            $borrowers[] = ['u' => $u, 'w' => $w];
            $lateProb[$u->id] = $late;
        }
        $B = fn (int $i) => $borrowers[$i]['u'];

        /* ---------- known maintenance ---------- */
        $unit = fn (string $code) => ItemUnit::where('asset_code', $code)->first();
        $repair = function (string $code, Carbon $from, ?Carbon $to, string $issue, float $cost = 0) use ($unit, $staff) {
            $u = $unit($code);
            MaintenanceLog::create(['item_unit_id' => $u->id, 'reported_by' => $staff[0]->id, 'issue' => $issue, 'status' => $to ? 'done' : 'open', 'cost' => $cost, 'started_at' => $from, 'finished_at' => $to]);
            if (! $to) {
                $u->update(['status' => 'maintenance', 'condition' => 'damaged']);
            }
        };
        $repair('CAM-002', $now->copy()->subDays(38), null, 'Lensa tidak bisa fokus otomatis');
        $repair('CAM-003', $now->copy()->subDays(21), null, 'Sensor berdebu dan layar retak');
        $repair('SPK-002', $now->copy()->subDays(60), $now->copy()->subDays(52), 'Baterai menggembung, diganti', 450000);
        $repair('PRJ-004', $now->copy()->subDays(84), $now->copy()->subDays(80), 'Lampu redup', 380000);
        $repair('PRJ-004', $now->copy()->subDays(52), $now->copy()->subDays(49), 'Kipas berisik dan panas', 150000);
        $repair('PRJ-004', $now->copy()->subDays(23), $now->copy()->subDays(19), 'Port HDMI longgar', 90000);

        /* ---------- replay 18 months of requests ---------- */
        $this->command?->info('Memutar ulang riwayat peminjaman (butuh beberapa menit)...');
        $bundles = [[3, ['PRJ', 'HDM', 'TRM']], [2, ['CAM', 'TRP']], [2.5, ['MIC', 'SPK']], [1.6, ['KRS', 'MJA', 'TND']], [1.5, ['LTP', 'TRM']], [1, ['PRJ', 'SCR']], [1.4, ['WBD']], [1.4, ['WKT']], [2.6, ['PRJ']], [1.4, ['LTP']], [1.8, ['KRS']], [.8, ['TND']], [.8, ['CAM']], [.6, ['SPK']], [.6, ['MIC']]];
        $dow = [.15, 1.2, 1.35, 1.4, 1.2, .9, .4];
        $mon = [.6, 1.1, 1.3, 1.2, 1.2, .7, .5, .6, 1.2, 1.45, 1.3, .8];
        $hours = [7 => 1, 8 => 4, 9 => 5, 10 => 4, 11 => 2, 12 => 1, 13 => 3, 14 => 3, 15 => 2, 16 => 1];
        $qtyFor = fn (string $sku) => match ($sku) {
            'HDM', 'TRM' => $ri(1, 3), 'KRS' => $ri(10, 55), 'MJA' => $ri(4, 14), 'TND' => $ri(1, 2), 'WKT' => $ri(1, 3), default => 1,
        };
        $span = (int) env('SILABAR_SEED_DAYS', 520);
        $reqs = [];
        for ($d = -$span; $d <= 12; $d++) {
            $day = $today->copy()->addDays($d);
            $lam = 1.25 * $dow[$day->dayOfWeek] * $mon[$day->month - 1] * (0.75 + 0.5 * ($d + $span) / ($span + 12));
            $n = 0;
            $L = exp(-$lam);
            $p = 1.0;
            do {
                $n++;
                $p *= $R();
            } while ($p > $L);
            for ($k = 0; $k < $n - 1; $k++) {
                $skus = $wpick($bundles, fn ($x) => $x[0])[1];
                $bor = $wpick($borrowers, fn ($x) => $x['w'])['u'];
                $hr = $wpick(array_keys($hours), fn ($h) => $hours[$h]);
                $start = $day->copy()->setTime($hr, $ri(0, 3) * 15);
                $maxD = min(array_map(fn ($s) => $item[$s]->max_loan_days, $skus));
                $days = min($wpick([1, 2, 3], fn ($x) => [1 => 5, 2 => 3, 3 => 2][$x]), $maxD);
                $created = $start->copy()->subHours($ri(8, 6 * 24));
                if ($created->gt($now)) {
                    continue;
                }
                $reqs[] = ['created' => $created, 'start' => $start, 'due' => $start->copy()->addDays($days), 'bor' => $bor, 'skus' => $skus, 'purpose' => $pick(self::PURPOSES), 'qty' => array_map($qtyFor, array_combine($skus, $skus))];
            }
        }
        usort($reqs, fn ($a, $b) => $a['created']->timestamp <=> $b['created']->timestamp);

        $unitsFor = fn (Item $it, int $q) => ItemUnit::where('item_id', $it->id)->inRandomOrder()->limit($q)->pluck('id')->all();
        $expand = function (Loan $loan, bool $historical) use ($unitsFor) {
            foreach ($loan->details()->with('item')->get() as $d) {
                if (! $d->item->isUnit()) {
                    continue;
                }
                $ids = $historical ? $unitsFor($d->item, $d->qty) : ItemUnit::where('item_id', $d->item_id)->where('status', 'available')
                    ->whereNotIn('id', LoanDetail::whereNotNull('item_unit_id')->whereHas('loan', fn ($q) => $q->whereIn('status', ['borrowed', 'overdue']))->pluck('item_unit_id'))->limit($d->qty)->pluck('id')->all();
                foreach ($ids as $i => $uid) {
                    if ($i === 0) {
                        $d->update(['qty' => 1, 'item_unit_id' => $uid]);
                    } else {
                        LoanDetail::create(['loan_id' => $d->loan_id, 'item_id' => $d->item_id, 'item_unit_id' => $uid, 'qty' => 1]);
                    }
                    if (! $historical) {
                        ItemUnit::where('id', $uid)->update(['status' => 'loaned']);
                    }
                }
            }
        };
        $setStatus = fn (Loan $l, LoanStatus $s, array $extra = []) => $l->forceFill(['status' => $s] + $extra)->saveQuietly();
        $finishReturned = function (Loan $loan, Carbon $handed, Carbon $ret, User $stf) use ($expand, $R) {
            $expand($loan, true);
            $late = $ret->gt($loan->due_at) ? (int) ceil($loan->due_at->diffInSeconds($ret) / 86400) : 0;
            $loan->forceFill(['status' => LoanStatus::Returned, 'handed_over_at' => $handed, 'handed_over_by' => $stf->id, 'returned_at' => $ret, 'late_days' => $late])->saveQuietly();
            $rt = LoanReturn::create(['loan_id' => $loan->id, 'received_by' => $stf->id, 'returned_at' => $ret, 'late_days' => $late]);
            $damaged = false;
            foreach ($loan->details()->with('item')->get() as $d) {
                $cond = (! $late && ! $damaged && $d->item->isUnit() && $R() < .03 && $ret->lt(now()->subDays(14))) ? 'damaged' : 'good';
                $damaged = $damaged || $cond === 'damaged';
                ReturnDetail::create(['return_id' => $rt->id, 'loan_detail_id' => $d->id, 'condition_after' => $cond, 'qty_returned' => $d->qty]);
                if ($cond === 'damaged') {
                    Penalty::create(['return_id' => $rt->id, 'loan_id' => $loan->id, 'user_id' => $loan->borrower_id, 'type' => 'damaged', 'amount' => $d->item->replacement_value * .3, 'status' => 'paid', 'note' => 'Kerusakan ringan', 'created_at' => $ret, 'updated_at' => $ret]);
                }
            }
            if ($late) {
                $qty = (int) $loan->details()->sum('qty');
                Penalty::create(['return_id' => $rt->id, 'loan_id' => $loan->id, 'user_id' => $loan->borrower_id, 'type' => 'late', 'amount' => $late * 2000 * $qty,
                    'status' => ($R() < ($ret->lt(now()->subDays(20)) ? .92 : .5)) ? 'paid' : 'unpaid', 'note' => $late.' hari terlambat', 'created_at' => $ret, 'updated_at' => $ret]);
            }
        };

        DB::disableQueryLog();
        foreach ($reqs as $i => $q) {
            $lines = [];
            foreach ($q['skus'] as $sku) {
                $lines[$item[$sku]->id] = $q['qty'][$sku];
            }
            $res = $validator->validate($q['bor'], $lines, $q['start'], $q['due'], $q['created'], true);
            if (! $res['ok']) {
                $validator->recordUnmet($q['bor'], $res, $q['start'], $q['due'], $q['created']->copy()->addMinutes($ri(0, 30)));
                continue;
            }
            $loan = $loans->create($q['bor'], $lines, $q['start'], $q['due'], $q['purpose'], $q['created'], true);
            $stf = $pick($staff->all());
            $r = $R();
            if ($q['start']->gt($now)) {
                if ($loan->status === LoanStatus::Pending && $q['created']->lt($now->copy()->subHours(20))) {
                    $setStatus($loan, LoanStatus::Approved, ['approved_by' => $stf->id, 'approved_at' => $q['created']->copy()->addHours($ri(1, 8))]);
                }
                continue;
            }
            if ($r < .035) {
                $setStatus($loan, LoanStatus::Cancelled, ['cancel_note' => 'Dibatalkan peminjam']);
                continue;
            }
            if ($r < .065) {
                $loan->status === LoanStatus::Pending
                    ? $setStatus($loan, LoanStatus::Rejected, ['reject_reason' => $pick(self::REJECT), 'approved_by' => $stf->id, 'approved_at' => $q['created']->copy()->addHours($ri(1, 6))])
                    : $setStatus($loan, LoanStatus::Cancelled, ['cancel_note' => 'Dibatalkan peminjam']);
                continue;
            }
            if ($loan->status === LoanStatus::Pending) {
                $loan->forceFill(['approved_by' => $stf->id, 'approved_at' => $q['created']->copy()->addHours($ri(1, 8))])->saveQuietly();
            }
            $handed = $q['start']->copy()->addMinutes($ri(3, 40));
            $days = (int) ceil($q['start']->diffInSeconds($q['due']) / 86400);
            $pLate = max(0, min(.9, $lateProb[$q['bor']->id] + .14 * ($days - 1) + ($q['due']->isWeekend() ? .18 : 0) + (count($lines) > 2 ? .10 : 0)));
            $ret = $R() < $pLate ? $q['due']->copy()->addDays($ri(1, 3))->addHours($ri(0, 8)) : $q['due']->copy()->subSeconds((int) ($R() * .3 * $q['start']->diffInSeconds($q['due'])));
            if ($ret->lt($handed->copy()->addHour())) {
                $ret = $handed->copy()->addHour();
            }
            if ($ret->lte($now)) {
                $finishReturned($loan, $handed, $ret, $stf);
            } else {
                $loan->forceFill(['status' => $q['due']->lt($now) ? LoanStatus::Overdue : LoanStatus::Borrowed, 'handed_over_at' => $handed, 'handed_over_by' => $stf->id])->saveQuietly();
                $expand($loan, false);
            }
            if ($i % 100 === 0) {
                $this->command?->info("  {$i} / ".count($reqs));
            }
        }

        /* ---------- a live scenario for the demo accounts ---------- */
        $craft = function (User $u, array $skus, int $dayOff, int $hour, int $days, string $status, string $purpose, ?User $stf = null) use ($item, $loans, $expand, $now, $today) {
            $start = $today->copy()->addDays($dayOff)->setTime($hour, 0);
            $due = $start->copy()->addDays($days);
            $lines = [];
            foreach ($skus as $sku => $qty) {
                $lines[$item[$sku]->id] = $qty;
            }
            $loan = $loans->create($u, $lines, $start, $due, $purpose, $now->copy()->subHours(2), true);
            $loan->forceFill(['status' => LoanStatus::from($status), 'approved_by' => $stf?->id, 'approved_at' => $status === 'pending' ? null : $now->copy()->subHour()])->saveQuietly();
            if (in_array($status, ['borrowed', 'overdue'], true)) {
                $loan->forceFill(['handed_over_at' => $start->copy()->min($now->copy()->subHour()), 'handed_over_by' => $stf?->id])->saveQuietly();
                $expand($loan, false);
            }

            return $loan;
        };
        $craft($B(0), ['PRJ' => 1, 'HDM' => 1, 'TRM' => 1], -1, 14, 2, 'borrowed', 'Seminar himpunan: Pengantar Machine Learning', $staff[0]);
        $craft($B(0), ['CAM' => 1, 'TRP' => 1], 2, 9, 1, 'approved', 'Dokumentasi pameran karya mahasiswa', $staff[1]);
        $craft($B(2), ['WKT' => 2, 'WBD' => 1], 0, 8, 2, 'borrowed', 'Gladi resik wisuda', $staff[1]);
        $craft($B(11), ['TND' => 2, 'KRS' => 30, 'MJA' => 8], 0, 9, 3, 'borrowed', 'Bazar kampus', $staff[0]);
        $craft($B(5), ['MIC' => 2, 'SPK' => 1], -5, 10, 3, 'overdue', 'Konser amal himpunan musik', $staff[0]);
        $craft($B(7), ['MIC' => 1], -4, 13, 2, 'overdue', 'Latihan teater', $staff[1]);
        $craft($B(1), ['PRJ' => 1, 'HDM' => 1], 3, 8, 1, 'pending', 'Rapat kerja BEM');
        $craft($B(5), ['LTP' => 2], 4, 10, 2, 'pending', 'Workshop desain poster');
        $craft($B(7), ['TND' => 1, 'KRS' => 20], 5, 8, 2, 'pending', 'Pentas seni UKM Teater');
        $ext = $craft($B(2), ['WKT' => 1], -1, 9, 2, 'borrowed', 'Panitia lomba antar kelas', $staff[0]);
        LoanExtension::create(['loan_id' => $ext->id, 'days' => 1, 'reason' => 'Acara mundur satu hari karena hujan', 'status' => 'pending']);

        /* ---------- crafted unmet demand (context captured at failure time) ---------- */
        $um = function (string $sku, int $who, int $daysAgo, float $hour, int $qty, int $avail, string $reason, float $util, float $maint, int $od, int $reqOff = 2) use ($item, $B, $today) {
            $at = $today->copy()->subDays($daysAgo)->addMinutes((int) ($hour * 60));
            $start = $at->copy()->addDays($reqOff);
            UnmetDemandLog::create(['item_id' => $item[$sku]->id, 'user_id' => $B($who)->id, 'requested_qty' => $qty, 'available_qty' => $avail, 'requested_start_at' => $start,
                'requested_end_at' => $start->copy()->addDays(2), 'reason' => $reason, 'util' => $util, 'maint_share' => $maint, 'overdue_units' => $od, 'created_at' => $at]);
        };
        foreach ([1, 3, 4, 6, 8, 11, 13, 16, 19, 22, 25] as $i => $d) {
            $um('PRJ', ($i * 3 + 1) % 12, $d, 9 + $i % 5, 1 + $i % 2, 0, $i % 3 ? 'date_conflict' : 'out_of_stock', .94 + $R() * .06, 0, $i % 6 === 0 ? 1 : 0);
        }
        foreach ([2, 5, 7, 10, 14, 18] as $i => $d) {
            $um('CAM', ($i * 2 + 2) % 12, $d, 10 + $i % 4, 1, $i % 2 ? 0 : 1, $i % 2 ? 'date_conflict' : 'out_of_stock', .75 + $R() * .25, .67, 0);
        }
        foreach ([1, 2, 4, 6, 9, 12, 15] as $i => $d) {
            $um('MIC', ($i * 5 + 3) % 12, $d, 9 + $i % 5, 2, 1, $i % 2 ? 'date_conflict' : 'out_of_stock', .8 + $R() * .2, 0, 1 + $i % 2);
        }
        foreach ([3, 4, 10, 11, 17, 18, 24, 25] as $i => $d) {
            $off = ((8 - $today->copy()->subDays($d)->dayOfWeek) % 7) ?: 1; // next Monday
            $um('KRS', ($i * 7 + 2) % 12, $d, 8, 35 + $i * 3, max(0, 25 - $i * 2), 'out_of_stock', .5 + $R() * .12, 0, 0, $off);
        }

        /* ---------- derived data ---------- */
        foreach ($borrowers as $b) {
            $trust->refresh($b['u']);
        }
        Penalty::where('user_id', $B(0)->id)->update(['status' => 'paid']); // the main demo borrower starts with a clean slate
        $trust->refresh($B(0));
        $this->command?->info('Mengagregasi statistik harian...');
        $stats->aggregateRange($today->copy()->subDays($span), $today);

        $rina = $B(0);
        $notifier->send($rina, 'Tiket untuk kamera disetujui. Ambil dua hari lagi dengan QR tiketmu.', 'loans.index');
        $notifier->send($rina, 'Pengingat: proyektor jatuh tempo besok sore.', 'loans.index');
        $notifier->send($staff->all(), 'Ada 3 pengajuan baru menunggu persetujuan.', 'staff.approvals');
        $notifier->send($staff->all(), '2 pinjaman mikrofon terlambat dikembalikan.', 'staff.counter');
        foreach (Loan::with('details')->where('created_at', '>', $now->copy()->subDays(14))->get() as $l) {
            ActivityLog::create(['causer_id' => $l->borrower_id, 'action' => 'Mengajukan peminjaman', 'subject_type' => $l->loan_code, 'properties' => null, 'created_at' => $l->created_at]);
            if ($l->approved_by) {
                ActivityLog::create(['causer_id' => $l->approved_by, 'action' => $l->status === LoanStatus::Rejected ? 'Menolak peminjaman' : 'Menyetujui peminjaman', 'subject_type' => $l->loan_code, 'properties' => $l->reject_reason, 'created_at' => $l->approved_at]);
            }
            if ($l->handed_over_at) {
                ActivityLog::create(['causer_id' => $l->handed_over_by, 'action' => 'Serah terima barang', 'subject_type' => $l->loan_code, 'created_at' => $l->handed_over_at]);
            }
            if ($l->returned_at) {
                ActivityLog::create(['causer_id' => $l->handed_over_by, 'action' => 'Menerima pengembalian', 'subject_type' => $l->loan_code, 'properties' => 'selesai', 'created_at' => $l->returned_at]);
            }
        }

        if ($client->health()) {
            $this->command?->info('Menjalankan analisis Python...');
            $runner->run(['all']);
        } else {
            $this->command?->warn('Layanan analitik Python belum menyala. Setelah menyalakannya, jalankan: php artisan analytics:run');
        }
        $this->command?->info('Data demo siap. Masuk dengan rina@mhs.silabar.id / agus@silabar.id / admin@silabar.id, kata sandi: '.$pw);
    }
}
