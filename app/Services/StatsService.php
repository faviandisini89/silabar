<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\DailyItemStat;
use App\Models\DemandForecast;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanDetail;
use App\Models\OverdueRiskScore;
use App\Models\UnmetDemandLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Descriptive statistics behind both dashboards (no ML here: this works from day one). */
class StatsService
{
    public const PERIODS = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan'];
    public const WINDOW_LABEL = ['harian' => 'hari ini', 'mingguan' => '7 hari terakhir', 'bulanan' => '30 hari terakhir', 'tahunan' => '365 hari terakhir'];

    public function __construct(private AvailabilityService $availability)
    {
    }

    private function counted(): array
    {
        return LoanStatus::values(LoanStatus::counted());
    }

    public function windowStart(string $period): Carbon
    {
        return match ($period) {
            'harian' => now()->startOfDay(),
            'mingguan' => now()->subDays(7),
            'tahunan' => now()->subDays(365),
            default => now()->subDays(30),
        };
    }

    /** Nightly aggregation into daily_item_stats (idempotent). */
    public function aggregateDay(Carbon $day): int
    {
        $from = $day->copy()->startOfDay();
        $to = $from->copy()->addDay();
        $rows = LoanDetail::query()->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->whereIn('loans.status', $this->counted())->where('loans.start_at', '>=', $from)->where('loans.start_at', '<', $to)
            ->get(['loan_details.item_id', 'loan_details.qty', 'loans.id as loan_id', 'loans.borrower_id', 'loans.start_at', 'loans.due_at'])
            ->groupBy('item_id');
        $unmet = UnmetDemandLog::where('created_at', '>=', $from)->where('created_at', '<', $to)->selectRaw('item_id, COUNT(*) c')->groupBy('item_id')->pluck('c', 'item_id');
        $n = 0;
        foreach (Item::pluck('id') as $itemId) {
            $g = $rows->get($itemId, collect());
            $u = (int) ($unmet[$itemId] ?? 0);
            if ($g->isEmpty() && $u === 0) {
                DailyItemStat::where('stat_date', $from->toDateString())->where('item_id', $itemId)->delete();
                continue;
            }
            DailyItemStat::updateOrCreate(['stat_date' => $from->toDateString(), 'item_id' => $itemId], [
                'loan_count' => $g->pluck('loan_id')->unique()->count(),
                'qty_borrowed' => (int) $g->sum('qty'),
                'unique_borrowers' => $g->pluck('borrower_id')->unique()->count(),
                'avg_duration_days' => $g->isEmpty() ? 0 : round($g->avg(fn ($r) => (Carbon::parse($r->due_at)->timestamp - Carbon::parse($r->start_at)->timestamp) / 86400), 2),
                'unmet_requests' => $u,
            ]);
            $n++;
        }

        return $n;
    }

    public function aggregateRange(Carbon $from, Carbon $to): int
    {
        $n = 0;
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $n += $this->aggregateDay($d);
        }

        return $n;
    }

    /** @return Collection<int,object{item:Item,n:int,q:int}> */
    public function topItems(string $period, ?int $categoryId = null, ?int $userId = null, int $limit = 10): Collection
    {
        $from = $this->windowStart($period);
        $today = now()->startOfDay();
        $live = fn (Carbon $a) => LoanDetail::query()->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->whereIn('loans.status', $this->counted())->where('loans.start_at', '>=', $a)
            ->when($userId, fn ($q) => $q->where('loans.borrower_id', $userId))
            ->selectRaw('loan_details.item_id, COUNT(DISTINCT loans.id) n, SUM(loan_details.qty) q')->groupBy('loan_details.item_id')->get();
        if ($userId) {
            $agg = $live($from)->keyBy('item_id');
        } else {
            $hist = DailyItemStat::where('stat_date', '>=', $from->toDateString())->where('stat_date', '<', $today->toDateString())
                ->selectRaw('item_id, SUM(loan_count) n, SUM(qty_borrowed) q')->groupBy('item_id')->get()->keyBy('item_id');
            $agg = $hist;
            foreach ($live(max($from, $today)) as $r) {
                $h = $agg->get($r->item_id);
                $agg[$r->item_id] = (object) ['item_id' => $r->item_id, 'n' => ($h->n ?? 0) + $r->n, 'q' => ($h->q ?? 0) + $r->q];
            }
        }
        $items = Item::whereIn('id', $agg->keys())->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))->get()->keyBy('id');

        return $agg->filter(fn ($r) => $items->has($r->item_id))
            ->map(fn ($r) => (object) ['item' => $items[$r->item_id], 'n' => (int) $r->n, 'q' => (int) $r->q])
            ->sortByDesc(fn ($r) => $r->n * 100000 + $r->q)->take($limit)->values();
    }

    /** @return list<array{label:string,v:int,cur:bool}> */
    public function trend(string $period, ?int $userId = null): array
    {
        [$buckets, $keyFn] = $this->buckets($period);
        $from = $buckets[0]['from'];
        $starts = Loan::whereIn('status', $this->counted())->where('start_at', '>=', $from)->when($userId, fn ($q) => $q->where('borrower_id', $userId))->pluck('start_at');
        $counts = array_fill_keys(array_column($buckets, 'key'), 0);
        foreach ($starts as $s) {
            $k = $keyFn(Carbon::parse($s));
            if (isset($counts[$k])) {
                $counts[$k]++;
            }
        }

        return array_map(fn ($b) => ['label' => $b['label'], 'v' => $counts[$b['key']], 'cur' => $b['cur']], $buckets);
    }

    private function buckets(string $period): array
    {
        $now = now();
        $out = [];
        switch ($period) {
            case 'harian':
                for ($i = 13; $i >= 0; $i--) {
                    $d = $now->copy()->subDays($i)->startOfDay();
                    $out[] = ['key' => $d->toDateString(), 'label' => (string) $d->day, 'from' => $d, 'cur' => $i === 0];
                }
                $fn = fn (Carbon $c) => $c->toDateString();
                break;
            case 'mingguan':
                for ($i = 11; $i >= 0; $i--) {
                    $d = $now->copy()->startOfWeek()->subWeeks($i);
                    $out[] = ['key' => $d->toDateString(), 'label' => $d->day.'/'.$d->month, 'from' => $d, 'cur' => $i === 0];
                }
                $fn = fn (Carbon $c) => $c->copy()->startOfWeek()->toDateString();
                break;
            case 'tahunan':
                for ($i = 1; $i >= 0; $i--) {
                    $d = $now->copy()->subYears($i)->startOfYear();
                    $out[] = ['key' => (string) $d->year, 'label' => (string) $d->year, 'from' => $d, 'cur' => $i === 0];
                }
                $fn = fn (Carbon $c) => (string) $c->year;
                break;
            default:
                for ($i = 11; $i >= 0; $i--) {
                    $d = $now->copy()->startOfMonth()->subMonths($i);
                    $out[] = ['key' => $d->format('Y-m'), 'label' => $d->translatedFormat('M'), 'from' => $d, 'cur' => $i === 0];
                }
                $fn = fn (Carbon $c) => $c->format('Y-m');
        }

        return [$out, $fn];
    }

    /** @return array{grid:array<int,array<int,int>>,max:int,best:array{0:int,1:int,2:int}} Monday=0, hours 07..17 */
    public function heatmap(int $days = 180): array
    {
        $grid = array_fill(0, 7, array_fill(0, 11, 0));
        foreach (Loan::whereIn('status', $this->counted())->where('start_at', '>=', now()->subDays($days))->pluck('start_at') as $s) {
            $c = Carbon::parse($s);
            $h = $c->hour - 7;
            if ($h >= 0 && $h <= 10) {
                $grid[$c->dayOfWeekIso - 1][$h]++;
            }
        }
        $max = 1;
        $best = [0, 0, 0];
        foreach ($grid as $d => $row) {
            foreach ($row as $h => $n) {
                $max = max($max, $n);
                if ($n > $best[2]) {
                    $best = [$d, $h + 7, $n];
                }
            }
        }

        return ['grid' => $grid, 'max' => $max, 'best' => $best];
    }

    /** Average share of owned stock that was out on loan, last $days days (0..1). */
    public function utilization(Item $item, int $days = 30): float
    {
        $total = max(1, $item->ownedCount());
        $now = now();
        $rows = LoanDetail::query()->join('loans', 'loans.id', '=', 'loan_details.loan_id')
            ->where('loan_details.item_id', $item->id)->whereIn('loans.status', $this->counted())
            ->where('loans.handed_over_at', '<', $now)->get(['loan_details.qty', 'loans.handed_over_at', 'loans.due_at', 'loans.returned_at', 'loans.status'])
            ->map(fn ($r) => [
                'a' => Carbon::parse($r->handed_over_at)->timestamp,
                'b' => ($r->status === 'returned' ? Carbon::parse($r->returned_at) : Carbon::parse($r->due_at)->max($now))->timestamp,
                'q' => (int) $r->qty,
            ]);
        $acc = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $s = $now->copy()->startOfDay()->subDays($i)->timestamp;
            $e = $s + 86400;
            $occ = $rows->filter(fn ($r) => $r['a'] < $e && $r['b'] > $s)->sum('q');
            $acc += min(1, $occ / $total);
        }

        return $acc / $days;
    }

    public function kpis(): array
    {
        $active = Loan::whereIn('status', ['borrowed', 'overdue'])->count();
        $overdue = Loan::where('status', 'overdue')->count();
        $ret = Loan::where('status', 'returned')->where('returned_at', '>', now()->subDays(90))->get(['late_days']);
        $utils = Item::all()->map(fn ($i) => $this->utilization($i));
        $weekly = [];
        for ($i = 7; $i >= 0; $i--) {
            $s = now()->startOfWeek()->subWeeks($i);
            $weekly[] = Loan::whereIn('status', $this->counted())->where('start_at', '>=', $s)->where('start_at', '<', $s->copy()->addWeek())->count();
        }

        return [
            'active' => $active, 'overdue' => $overdue,
            'on_time' => $ret->isEmpty() ? 1.0 : $ret->where('late_days', 0)->count() / $ret->count(),
            'utilization' => $utils->avg() ?? 0,
            'month' => Loan::whereIn('status', $this->counted())->where('start_at', '>=', now()->startOfMonth())->count(),
            'last_month' => Loan::whereIn('status', $this->counted())->whereBetween('start_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()])->count(),
            'weekly' => $weekly,
        ];
    }

    /** @return array{history:list<int>,labels:list<string>,forecast:list<array>,model:?string} */
    public function forecastSeries(Item $item, int $weeks = 16): array
    {
        $hist = [];
        $labels = [];
        for ($i = $weeks; $i >= 1; $i--) {
            $s = now()->startOfWeek()->subWeeks($i);
            $hist[] = Loan::whereIn('status', $this->counted())->where('start_at', '>=', $s)->where('start_at', '<', $s->copy()->addWeek())
                ->whereHas('details', fn ($q) => $q->where('item_id', $item->id))->count();
            $labels[] = $s->day.'/'.$s->month;
        }
        $fc = DemandForecast::where('item_id', $item->id)->where('forecast_date', '>=', now()->startOfWeek()->toDateString())->orderBy('forecast_date')->limit(4)->get();
        foreach ($fc as $k => $f) {
            $labels[] = '+'.($k + 1).' mgg';
        }

        return ['history' => $hist, 'labels' => $labels, 'forecast' => $fc->map(fn ($f) => ['v' => $f->predicted_qty, 'lo' => $f->lower_bound, 'hi' => $f->upper_bound])->all(), 'model' => $fc->first()?->model_name];
    }

    public function riskList(int $limit = 6): Collection
    {
        return OverdueRiskScore::with(['loan.borrower', 'loan.details.item'])
            ->whereHas('loan', fn ($q) => $q->whereIn('status', ['pending', 'approved', 'borrowed']))
            ->orderByDesc('risk_probability')->limit($limit)->get();
    }
}
