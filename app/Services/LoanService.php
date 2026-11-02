<?php

namespace App\Services;

use App\Domain\LoanRules;
use App\Domain\PenaltyCalculator;
use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\LoanExtension;
use App\Models\LoanReturn;
use App\Models\MaintenanceLog;
use App\Models\Penalty;
use App\Models\ReturnDetail;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanService
{
    public function __construct(
        private SettingsService $settings,
        private AvailabilityService $availability,
        private Notifier $notifier,
        private AuditLogger $audit,
        private TrustScoreService $trust,
    ) {
    }

    /** @param array<int,int> $lines item_id => qty (already validated) */
    public function create(User $user, array $lines, Carbon $start, Carbon $end, string $purpose, ?Carbon $at = null, bool $silent = false): Loan
    {
        return DB::transaction(function () use ($user, $lines, $start, $end, $purpose, $at, $silent) {
            $at ??= now();
            $items = Item::whereIn('id', array_keys($lines))->get();
            $needs = LoanRules::needsApproval(
                $items->contains(fn (Item $i) => $i->requires_approval),
                $this->settings->bool('loan.auto_approve'),
                $user->trust_score,
                $this->settings->int('loan.auto_approve_trust'),
            );
            $loan = (new Loan)->forceFill([
                'loan_code' => 'TMP'.Str::random(14), 'borrower_id' => $user->id, 'status' => $needs ? LoanStatus::Pending : LoanStatus::Approved,
                'purpose' => $purpose, 'start_at' => $start, 'due_at' => $end, 'approved_at' => $needs ? null : $at,
                'qr_token' => 'SLB'.strtoupper(Str::random(10)), 'created_at' => $at, 'updated_at' => $at,
            ]);
            $loan->save();
            $loan->loan_code = 'SLB-'.$at->format('ym').'-'.str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT);
            $loan->saveQuietly();
            foreach ($lines as $itemId => $qty) {
                LoanDetail::create(['loan_id' => $loan->id, 'item_id' => $itemId, 'qty' => $qty]);
            }
            if (! $silent) {
                $this->audit->log('Mengajukan peminjaman', $loan->loan_code, $items->map(fn ($i) => $i->name.' ×'.$lines[$i->id])->implode(', '), $user->id);
                if ($needs) {
                    $this->notifier->toStaff("Pengajuan baru {$loan->loan_code} dari {$user->name} menunggu persetujuan.", 'staff.approvals');
                } else {
                    $this->notifier->send($user, "Pengajuan {$loan->loan_code} disetujui otomatis. Ambil barang pada ".$start->translatedFormat('j M, H.i').'.', 'loans.index');
                }
            }

            return $loan->fresh(['details.item']);
        });
    }

    public function move(Loan $loan, LoanStatus $to): void
    {
        if (! $loan->status->canTransitionTo($to)) {
            throw new DomainException("Status {$loan->status->label()} tidak bisa langsung menjadi {$to->label()}.");
        }
        $loan->status = $to;
    }

    public function approve(Loan $loan, User $staff): void
    {
        DB::transaction(function () use ($loan, $staff) {
            foreach ($loan->details->groupBy('item_id') as $itemId => $rows) {
                $item = Item::find($itemId);
                $a = $this->availability->availability($item, $loan->start_at, $loan->due_at, $loan->id);
                if ($rows->sum('qty') > $a['avail']) {
                    throw new DomainException("{$item->name}: stok pada jadwal ini sudah terpakai ({$a['avail']} tersisa).");
                }
            }
            $this->move($loan, LoanStatus::Approved);
            $loan->fill(['approved_by' => $staff->id, 'approved_at' => now()])->save();
            $this->notifier->send($loan->borrower, "Pengajuan {$loan->loan_code} disetujui. Ambil barang pada ".$loan->start_at->translatedFormat('j M, H.i').' dengan tiket QR.', 'loans.index');
            $this->audit->log('Menyetujui peminjaman', $loan->loan_code, null, $staff->id);
        });
    }

    public function reject(Loan $loan, User $staff, string $reason): void
    {
        $this->move($loan, LoanStatus::Rejected);
        $loan->fill(['reject_reason' => $reason, 'approved_by' => $staff->id, 'approved_at' => now()])->save();
        $this->notifier->send($loan->borrower, "Pengajuan {$loan->loan_code} ditolak: {$reason}", 'loans.index');
        $this->audit->log('Menolak peminjaman', $loan->loan_code, $reason, $staff->id);
    }

    public function cancel(Loan $loan, User $by, ?string $note = null): void
    {
        $this->move($loan, LoanStatus::Cancelled);
        $loan->fill(['cancel_note' => $note ?: 'Dibatalkan peminjam'])->save();
        $this->audit->log('Membatalkan peminjaman', $loan->loan_code, null, $by->id);
    }

    /**
     * @param  array<int,list<string>>  $unitCodes  loan_detail_id => scanned asset codes (unit-tracked items)
     * @param  array<int,bool>  $bulkConfirmed  loan_detail_id => quantity counted (bulk items)
     */
    public function handover(Loan $loan, User $staff, array $unitCodes, array $bulkConfirmed): void
    {
        DB::transaction(function () use ($loan, $staff, $unitCodes, $bulkConfirmed) {
            if ($loan->status !== LoanStatus::Approved) {
                throw new DomainException('Hanya tiket berstatus Disetujui yang bisa diserahkan.');
            }
            $loan->load('details.item');
            $used = [];
            foreach ($loan->details as $d) {
                if (! $d->item->isUnit()) {
                    if (empty($bulkConfirmed[$d->id])) {
                        throw new DomainException("Konfirmasi jumlah {$d->item->name} sudah dihitung.");
                    }
                    continue;
                }
                $codes = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim($c)), $unitCodes[$d->id] ?? []))));
                if (count($codes) !== $d->qty) {
                    throw new DomainException("{$d->item->name}: pindai {$d->qty} unit (baru {$this->n($codes)}).");
                }
                $units = ItemUnit::whereIn('asset_code', $codes)->lockForUpdate()->get();
                if ($units->count() !== count($codes)) {
                    throw new DomainException("{$d->item->name}: ada kode unit yang tidak dikenal.");
                }
                foreach ($units as $u) {
                    if ($u->item_id !== $d->item_id) {
                        throw new DomainException("{$u->asset_code} bukan {$d->item->name}.");
                    }
                    if ($u->status->value !== 'available' || in_array($u->id, $used, true)) {
                        throw new DomainException("{$u->asset_code} tidak tersedia ({$u->status->label()}).");
                    }
                    $used[] = $u->id;
                }
                // one detail row per physical unit
                foreach ($units->values() as $i => $u) {
                    if ($i === 0) {
                        $d->update(['qty' => 1, 'item_unit_id' => $u->id]);
                    } else {
                        LoanDetail::create(['loan_id' => $loan->id, 'item_id' => $d->item_id, 'item_unit_id' => $u->id, 'qty' => 1]);
                    }
                    $u->update(['status' => 'loaned']);
                }
            }
            $this->move($loan, LoanStatus::Borrowed);
            $loan->fill(['handed_over_by' => $staff->id, 'handed_over_at' => now()])->save();
            $this->notifier->send($loan->borrower, "Barang untuk {$loan->loan_code} sudah diserahkan. Jatuh tempo ".$loan->due_at->translatedFormat('j M, H.i').'.', 'loans.index');
            $this->audit->log('Serah terima barang', $loan->loan_code, null, $staff->id);
        });
    }

    private function n(array $a): int
    {
        return count($a);
    }

    /**
     * @param  array<int,array{qty:int,condition:string}>  $rows  loan_detail_id => returned quantity and condition
     * @return array{done:bool,late_days:int,penalty:float}
     */
    public function receiveReturn(Loan $loan, User $staff, array $rows, ?string $notes = null): array
    {
        return DB::transaction(function () use ($loan, $staff, $rows, $notes) {
            if (! in_array($loan->status, [LoanStatus::Borrowed, LoanStatus::Overdue], true)) {
                throw new DomainException('Tiket ini tidak sedang dipinjam.');
            }
            $loan->load('details.item', 'details.unit');
            $calc = new PenaltyCalculator($this->settings->float('penalty.late_per_day'), $this->settings->float('penalty.damaged_pct'));
            $lateDays = PenaltyCalculator::lateDays($loan->due_at->timestamp, now()->timestamp);
            $ret = LoanReturn::create(['loan_id' => $loan->id, 'received_by' => $staff->id, 'returned_at' => now(), 'late_days' => $lateDays, 'notes' => $notes]);
            $returned = 0;
            $damaged = 0.0;
            $lost = 0.0;
            foreach ($loan->details as $d) {
                $row = $rows[$d->id] ?? null;
                $qty = $row ? min((int) $row['qty'], $d->outstandingQty()) : 0;
                if ($qty <= 0) {
                    continue;
                }
                $cond = in_array($row['condition'], ['good', 'fair', 'damaged', 'lost'], true) ? $row['condition'] : 'good';
                ReturnDetail::create(['return_id' => $ret->id, 'loan_detail_id' => $d->id, 'condition_after' => $cond, 'qty_returned' => $qty]);
                $returned += $qty;
                $item = $d->item;
                if ($d->unit) {
                    match ($cond) {
                        'lost' => $d->unit->update(['status' => 'lost']),
                        'damaged' => $this->sendToRepair($d->unit, $staff, "Rusak saat dikembalikan ({$loan->loan_code})"),
                        default => $d->unit->update(['status' => 'available', 'condition' => $cond]),
                    };
                } elseif (in_array($cond, ['damaged', 'lost'], true)) {
                    $item->update(['total_stock' => max(0, $item->total_stock - $qty)]); // written off
                }
                if ($cond === 'damaged') {
                    $damaged += $calc->damaged($item->replacement_value, $qty);
                }
                if ($cond === 'lost') {
                    $lost += $calc->lost($item->replacement_value, $qty);
                }
            }
            if ($returned === 0) {
                throw new DomainException('Isi jumlah barang yang kembali.');
            }
            $late = $calc->late($lateDays, $returned);
            foreach ([['late', $late, $lateDays.' hari terlambat'], ['damaged', $damaged, 'Kerusakan saat pengembalian'], ['lost', $lost, 'Barang hilang']] as [$type, $amt, $note]) {
                if ($amt > 0) {
                    Penalty::create(['return_id' => $ret->id, 'loan_id' => $loan->id, 'user_id' => $loan->borrower_id, 'type' => $type, 'amount' => $amt, 'status' => 'unpaid', 'note' => $note]);
                    $this->notifier->send($loan->borrower, 'Denda '.strtolower(\App\Enums\PenaltyType::from($type)->label()).' Rp'.number_format($amt, 0, ',', '.')." untuk {$loan->loan_code}.", 'history');
                }
            }
            $done = $loan->details->every(fn (LoanDetail $d) => $d->outstandingQty() === 0);
            if ($done) {
                $this->move($loan, LoanStatus::Returned);
                $loan->fill(['returned_at' => now(), 'late_days' => $loan->returns()->max('late_days') ?? 0])->save();
                $this->notifier->send($loan->borrower, "Pengembalian {$loan->loan_code} selesai. Terima kasih!", 'history');
            }
            $this->trust->refresh($loan->borrower);
            $this->audit->log('Menerima pengembalian', $loan->loan_code, $done ? 'selesai' : 'sebagian', $staff->id);

            return ['done' => $done, 'late_days' => $lateDays, 'penalty' => $late + $damaged + $lost];
        });
    }

    public function sendToRepair(ItemUnit $unit, ?User $by, string $issue): MaintenanceLog
    {
        $unit->update(['status' => 'maintenance', 'condition' => 'damaged']);

        return MaintenanceLog::create(['item_unit_id' => $unit->id, 'reported_by' => $by?->id, 'issue' => $issue, 'status' => 'open', 'started_at' => now()]);
    }

    public function requestExtension(Loan $loan, User $user, int $days, string $reason): LoanExtension
    {
        if ($loan->status !== LoanStatus::Borrowed) {
            throw new DomainException('Perpanjangan hanya untuk tiket yang sedang dipinjam.');
        }
        if ($loan->pendingExtension) {
            throw new DomainException('Sudah ada permintaan perpanjangan yang menunggu.');
        }
        $ext = LoanExtension::create(['loan_id' => $loan->id, 'days' => $days, 'reason' => $reason]);
        $this->notifier->toStaff("{$user->name} meminta perpanjangan {$days} hari untuk {$loan->loan_code}.", 'staff.approvals');
        $this->audit->log('Meminta perpanjangan', $loan->loan_code, "{$days} hari", $user->id);

        return $ext;
    }

    public function decideExtension(LoanExtension $ext, User $staff, bool $approve, ?string $note = null): void
    {
        DB::transaction(function () use ($ext, $staff, $approve, $note) {
            $loan = $ext->loan->load('details.item');
            if ($approve) {
                $newEnd = $loan->due_at->copy()->addDays($ext->days);
                foreach ($loan->details->groupBy('item_id') as $itemId => $rows) {
                    $item = Item::find($itemId);
                    $a = $this->availability->availability($item, $loan->due_at, $newEnd, $loan->id);
                    if ($rows->sum('qty') > $a['avail']) {
                        throw new DomainException("{$item->name} sudah dipesan setelah jatuh tempo.");
                    }
                }
                $loan->update(['due_at' => $newEnd, 'reminder_sent_at' => null]);
                $this->notifier->send($loan->borrower, "Perpanjangan {$loan->loan_code} disetujui. Jatuh tempo baru ".$newEnd->translatedFormat('j M, H.i').'.', 'loans.index');
            } else {
                $this->notifier->send($loan->borrower, "Perpanjangan {$loan->loan_code} ditolak: {$note}", 'loans.index');
            }
            $ext->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $staff->id, 'decided_at' => now(), 'note' => $note]);
            $this->audit->log($approve ? 'Menyetujui perpanjangan' : 'Menolak perpanjangan', $loan->loan_code, $note, $staff->id);
        });
    }
}
