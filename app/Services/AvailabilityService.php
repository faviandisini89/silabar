<?php

namespace App\Services;

use App\Domain\Reservations;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\MaintenanceLog;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    /** Units/quantity that can be lent during a window that starts at $start (looked at from time $at). */
    public function usable(Item $item, Carbon $at, Carbon $start): int
    {
        if (! $item->isUnit()) {
            return $item->total_stock;
        }
        $from = $at->greaterThan($start) ? $at : $start;
        $unitIds = ItemUnit::where('item_id', $item->id)->where('status', '!=', UnitStatus::Lost->value)->pluck('id');
        $inRepair = MaintenanceLog::whereIn('item_unit_id', $unitIds)
            ->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('finished_at')->orWhere('finished_at', '>', $from))
            ->pluck('item_unit_id')->unique();

        return $unitIds->diff($inRepair)->count();
    }

    /** @return list<array{start:int,end:int,qty:int}> */
    public function reservations(Item $item, Carbon $start, Carbon $end, ?int $excludeLoanId = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $now = now();
        $rows = LoanDetail::query()
            ->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->where('loan_details.item_id', $item->id)
            ->whereIn('loans.status', LoanStatus::values(LoanStatus::reserving()))
            ->when($excludeLoanId, fn ($q) => $q->where('loans.id', '!=', $excludeLoanId))
            ->where('loans.created_at', '<=', $at)
            ->where('loans.start_at', '<', $end)
            ->where(fn ($q) => $q->where('loans.due_at', '>', $start)->orWhere('loans.returned_at', '>', $start)->orWhere('loans.status', LoanStatus::Overdue->value))
            ->get(['loan_details.qty', 'loans.start_at', 'loans.due_at', 'loans.returned_at', 'loans.status']);

        return $rows->map(function ($r) use ($now) {
            $due = Carbon::parse($r->due_at);
            $end = match ($r->status) {
                'returned' => Carbon::parse($r->returned_at ?? $r->due_at),
                'overdue' => $due->greaterThan($now) ? $due : $now,
                default => $due,
            };

            return ['start' => Carbon::parse($r->start_at)->timestamp, 'end' => $end->timestamp, 'qty' => (int) $r->qty];
        })->all();
    }

    public function reserved(Item $item, Carbon $start, Carbon $end, ?int $excludeLoanId = null, ?Carbon $at = null): int
    {
        return Reservations::maxConcurrent($this->reservations($item, $start, $end, $excludeLoanId, $at), $start->timestamp, $end->timestamp);
    }

    /** @return array{usable:int,reserved:int,avail:int} */
    public function availability(Item $item, Carbon $start, Carbon $end, ?int $excludeLoanId = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $usable = $this->usable($item, $at, $start);
        $reserved = $this->reserved($item, $start, $end, $excludeLoanId, $at);

        return ['usable' => $usable, 'reserved' => $reserved, 'avail' => max(0, $usable - $reserved)];
    }

    /** Snapshot stored with an unmet-demand log: the diagnosis of "why" depends on it. */
    public function contextAt(Item $item, Carbon $at, Carbon $start, Carbon $end): array
    {
        $usable = $this->usable($item, $at, $start);
        $reserved = $this->reserved($item, $start, $end, null, $at);
        $owned = max(1, $item->ownedCount());
        $inRepair = 0;
        if ($item->isUnit()) {
            $ids = ItemUnit::where('item_id', $item->id)->pluck('id');
            $inRepair = MaintenanceLog::whereIn('item_unit_id', $ids)->where('started_at', '<=', $at)
                ->where(fn ($q) => $q->whereNull('finished_at')->orWhere('finished_at', '>', $at))->distinct('item_unit_id')->count('item_unit_id');
        }
        $overdue = Loan::whereNotNull('handed_over_at')->where('handed_over_at', '<=', $at)->where('due_at', '<', $at)
            ->where(fn ($q) => $q->whereNull('returned_at')->orWhere('returned_at', '>', $at))
            ->whereHas('details', fn ($q) => $q->where('item_id', $item->id))->count();

        return [
            'util' => $usable > 0 ? min(1.0, $reserved / $usable) : 1.0,
            'maint_share' => $inRepair / $owned,
            'overdue_units' => $overdue,
        ];
    }

    /** @return array<int,string> day-of-month => free|mid|full (past days are omitted) */
    public function dayLoads(Item $item, Carbon $month): array
    {
        $first = $month->copy()->startOfMonth();
        $last = $month->copy()->endOfMonth();
        $now = now();
        $usable = $this->usable($item, $now, $first->greaterThan($now) ? $first : $now);
        $res = $this->reservations($item, $first, $last->copy()->addDay(), null, $now);
        $out = [];
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            if ($d->copy()->endOfDay()->lt($now->copy()->startOfDay())) {
                continue;
            }
            $r = Reservations::maxConcurrent($res, $d->timestamp, $d->copy()->addDay()->timestamp);
            $out[(int) $d->format('j')] = Reservations::loadClass($usable, $r);
        }

        return $out;
    }
}
