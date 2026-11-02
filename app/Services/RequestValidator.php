<?php

namespace App\Services;

use App\Domain\RequestChecker;
use App\Enums\UnmetReason;
use App\Models\Item;
use App\Models\UnmetDemandLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class RequestValidator
{
    public function __construct(private AvailabilityService $availability)
    {
    }

    /**
     * @param  array<int,int>  $lines  item_id => qty
     */
    public function validate(User $user, array $lines, Carbon $start, Carbon $end, ?Carbon $at = null, bool $skipPenalty = false): array
    {
        $at ??= now();
        $items = Item::whereIn('id', array_keys($lines))->get()->keyBy('id');
        $prepared = [];
        foreach ($lines as $itemId => $qty) {
            $item = $items[$itemId];
            $prepared[] = [
                'item_id' => $item->id, 'name' => $item->name, 'qty' => (int) $qty, 'max_days' => $item->max_loan_days,
                'usable' => $this->availability->usable($item, $at, $start),
                'reserved' => $this->availability->reserved($item, $start, $end, null, $at),
            ];
        }

        return RequestChecker::check($prepared, $start->timestamp, $end->timestamp, $skipPenalty ? 0 : $user->unpaidAmount());
    }

    /** Persist every "stock" or "schedule" failure so the analysis can learn from missed demand. */
    public function recordUnmet(User $user, array $result, Carbon $start, Carbon $end, ?Carbon $at = null): int
    {
        $at ??= now();
        $n = 0;
        foreach ($result['fails'] as $f) {
            if (! in_array($f['code'], ['out_of_stock', 'date_conflict'], true)) {
                continue;
            }
            $item = Item::find($f['item_id']);
            $ctx = $this->availability->contextAt($item, $at, $start, $end);
            UnmetDemandLog::create([
                'item_id' => $item->id, 'user_id' => $user->id, 'requested_qty' => $f['qty'], 'available_qty' => $f['avail'],
                'requested_start_at' => $start, 'requested_end_at' => $end, 'reason' => UnmetReason::from($f['code'])->value,
                'util' => $ctx['util'], 'maint_share' => $ctx['maint_share'], 'overdue_units' => $ctx['overdue_units'], 'created_at' => $at,
            ]);
            $n++;
        }

        return $n;
    }

    /** First start (up to 4 weeks later) where every line fits. */
    public function suggestStart(array $lines, Carbon $start, Carbon $end): ?Carbon
    {
        $len = abs($end->timestamp - $start->timestamp);
        $items = Item::whereIn('id', array_keys($lines))->get()->keyBy('id');
        for ($d = 1; $d <= 28; $d++) {
            $s = $start->copy()->addDays($d);
            $e = $s->copy()->addSeconds($len);
            $fits = true;
            foreach ($lines as $itemId => $qty) {
                if ($qty > $this->availability->availability($items[$itemId], $s, $e)['avail']) {
                    $fits = false;
                    break;
                }
            }
            if ($fits) {
                return $s;
            }
        }

        return null;
    }
}
