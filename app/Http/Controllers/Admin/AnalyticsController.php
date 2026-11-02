<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisOutput;
use App\Models\Category;
use App\Models\Item;
use App\Models\ProcurementRecommendation;
use App\Services\AnalyticsClient;
use App\Services\AnalyticsRunner;
use App\Services\StatsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, StatsService $stats, AnalyticsClient $client)
    {
        $period = array_key_exists($request->query('p'), StatsService::PERIODS) ? $request->query('p') : 'bulanan';
        $cat = $request->query('cat');
        $items = Item::orderBy('name')->get();
        $fcItem = $items->firstWhere('id', (int) $request->query('fc')) ?? $items->first();
        $names = $items->pluck('name', 'id');

        $util = $items->map(fn ($i) => ['name' => $i->name, 'u' => $stats->utilization($i)])->sortByDesc('u')->values();
        $rules = collect(AnalysisOutput::latest_of('association_rules')?->payload ?? [])->take(5)->map(fn ($r) => $r + [
            'label' => collect(array_merge($r['antecedent'], [$r['consequent']]))->map(fn ($i) => $names[$i] ?? '?')->implode(' + '),
        ]);
        $summary = AnalysisOutput::latest_of('run_summary');

        $forecast = $fcItem
            ? $stats->forecastSeries($fcItem)
            : ['history' => [], 'labels' => [], 'forecast' => [], 'model' => null];

        return view('admin.analytics', [
            'period' => $period, 'cat' => $cat, 'categories' => Category::orderBy('name')->get(), 'items' => $items, 'fcItem' => $fcItem,
            'kpi' => $stats->kpis(), 'top' => $stats->topItems($period, $cat ? (int) $cat : null), 'trend' => $stats->trend($period),
            'heat' => $stats->heatmap(), 'util' => $util, 'forecast' => $forecast, 'risk' => $stats->riskList(),
            'anomalies' => collect(AnalysisOutput::latest_of('anomalies')?->payload ?? []), 'rules' => $rules,
            'recs' => ProcurementRecommendation::with('item')->where('status', 'open')->orderByDesc('priority_score')->limit(3)->get(),
            'summary' => $summary?->payload, 'summaryAt' => $summary?->generated_at,
            'riskReport' => AnalysisOutput::latest_of('risk_report')?->payload, 'online' => $client->health() !== null,
        ]);
    }

    public function run(Request $request, AnalyticsRunner $runner)
    {
        set_time_limit(0);
        $summary = $runner->run(['all'], $request->user()->id);
        if (($summary['status'] ?? '') === 'ok') {
            return back()->with('ok', 'Analisis selesai dalam '.($summary['seconds'] ?? '?').' detik.');
        }

        return back()->with('error', $summary['error'] ?? 'Sebagian tugas analisis gagal. Lihat log layanan Python.');
    }
}
