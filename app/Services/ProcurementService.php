<?php

namespace App\Services;

use App\Enums\RecStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ProcurementRecommendation as Rec;
use App\Models\UnmetDemandLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    public function __construct(private ItemService $items, private AuditLogger $audit)
    {
    }

    public function decide(Rec $rec, User $admin, RecStatus $status, ?string $note = null): void
    {
        if ($rec->status !== RecStatus::Open || ! in_array($status, [RecStatus::Approved, RecStatus::Deferred, RecStatus::Rejected], true)) {
            throw new DomainException('Rekomendasi ini sudah diputuskan.');
        }
        $rec->update(['status' => $status, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_note' => $note]);
        $this->audit->log('Rekomendasi pengadaan: '.$status->value, $rec->item->name, $note, $admin->id);
    }

    /** Record the purchase (or a non-purchase follow-up when $qty is 0) and start monitoring the fill rate. */
    public function fulfill(Rec $rec, User $admin, int $qty): void
    {
        if ($rec->status !== RecStatus::Approved) {
            throw new DomainException('Setujui rekomendasi lebih dulu.');
        }
        DB::transaction(function () use ($rec, $admin, $qty) {
            $item = $rec->item;
            if ($qty > 0) {
                if ($item->isUnit()) {
                    $this->items->addUnits($item, $qty);
                } else {
                    $item->increment('total_stock', $qty);
                }
            }
            $rec->update(['status' => RecStatus::Fulfilled, 'fulfilled_at' => now(), 'added_qty' => $qty, 'fill_before' => $rec->fill_rate]);
            $this->audit->log('Menambah stok dari rekomendasi', $item->name, '+'.$qty, $admin->id);
        });
    }

    /** @return array{fill:float,ok:int,unmet:int} */
    public function fillSince(Item $item, Carbon $from): array
    {
        $unmet = UnmetDemandLog::where('item_id', $item->id)->where('created_at', '>=', $from)->count();
        $ok = Loan::where('created_at', '>=', $from)->whereNotIn('status', ['rejected', 'cancelled'])->whereHas('details', fn ($q) => $q->where('item_id', $item->id))->count();

        return ['fill' => $ok + $unmet ? $ok / ($ok + $unmet) : 1.0, 'ok' => $ok, 'unmet' => $unmet];
    }
}
