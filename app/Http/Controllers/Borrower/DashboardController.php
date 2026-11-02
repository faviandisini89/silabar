<?php

namespace App\Http\Controllers\Borrower;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalysisOutput;
use App\Models\Item;
use App\Models\Loan;
use App\Services\StatsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $u = $request->user();
        $period = array_key_exists($request->query('p'), StatsService::PERIODS) ? $request->query('p') : 'mingguan';
        $mine = Loan::with(['details.item'])->where('borrower_id', $u->id);
        $active = (clone $mine)->whereIn('status', LoanStatus::values(LoanStatus::active()))->orderBy('start_at')->get();
        $returned = (clone $mine)->where('status', 'returned')->get(['late_days']);
        $onTime = $returned->isEmpty() ? 1.0 : $returned->where('late_days', 0)->count() / $returned->count();
        $next = $active->whereIn('status', [LoanStatus::Borrowed, LoanStatus::Overdue])->sortBy('due_at')->first();

        $trend = $stats->trend($period, $u->id);
        $total = array_sum(array_column($trend, 'v'));

        // "borrowed together" suggestions come from the Python association-rule output
        $names = Item::pluck('name', 'id');
        $rules = collect(AnalysisOutput::latest_of('association_rules')?->payload ?? [])->take(3)->map(fn ($r) => [
            'ids' => array_merge($r['antecedent'], [$r['consequent']]),
            'label' => collect(array_merge($r['antecedent'], [$r['consequent']]))->map(fn ($i) => $names[$i] ?? '?')->implode(' + '),
            'conf' => $r['confidence'],
        ]);

        return view('borrower.dashboard', [
            'user' => $u, 'active' => $active, 'next' => $next, 'period' => $period, 'trend' => $trend,
            'trendTotal' => $total, 'trendAvg' => $total / max(1, count($trend)), 'trendMax' => max(array_column($trend, 'v') ?: [0]),
            'kpi' => ['total' => (clone $mine)->whereIn('status', LoanStatus::values(LoanStatus::counted()))->count(), 'on_time' => $onTime, 'unpaid' => $u->unpaidAmount()],
            'favorites' => $stats->topItems('tahunan', null, $u->id, 5),
            'rules' => $rules,
            'feed' => $u->notifications()->limit(6)->get(),
        ]);
    }
}
