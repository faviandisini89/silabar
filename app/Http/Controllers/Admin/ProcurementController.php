<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecStatus;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\ProcurementRecommendation as Rec;
use App\Models\UnmetDemandLog;
use App\Services\AnalyticsRunner;
use App\Services\ProcurementService;
use App\Services\SettingsService;
use DomainException;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function index(Request $request, SettingsService $settings, ProcurementService $svc)
    {
        $recs = Rec::with('item')->get();
        $open = $recs->where('status', RecStatus::Open)->sortByDesc('priority_score')->values();
        $decided = $recs->whereIn('status', [RecStatus::Approved, RecStatus::Deferred, RecStatus::Rejected])->sortByDesc('reviewed_at')->values();
        $done = $recs->where('status', RecStatus::Fulfilled)->sortByDesc('fulfilled_at')->values();
        $tab = in_array($request->query('tab'), ['dec', 'ful'], true) ? $request->query('tab') : 'open';
        $list = ['open' => $open, 'dec' => $decided, 'ful' => $done][$tab];

        $window = $settings->int('procure.window_days');
        $from = now()->subDays($window);
        $unmet = UnmetDemandLog::where('created_at', '>', $from)->count();
        $ok = Loan::where('created_at', '>', $from)->whereNotIn('status', ['rejected', 'cancelled'])->count();
        $logs = UnmetDemandLog::with('user')->where('created_at', '>', $from)->latest('created_at')->get()->groupBy('item_id');
        $after = $done->mapWithKeys(fn ($r) => [$r->id => $svc->fillSince($r->item, $r->fulfilled_at)]);

        return view('admin.procure', [
            'tab' => $tab, 'list' => $list, 'counts' => ['open' => $open->count(), 'dec' => $decided->count(), 'ful' => $done->count()],
            'kpi' => ['open' => $open->count(), 'fill' => $ok + $unmet ? $ok / ($ok + $unmet) : 1, 'unmet' => $unmet, 'units' => $open->sum('recommended_add_qty')],
            'logs' => $logs, 'after' => $after, 's' => $settings->all(), 'window' => $window,
            'lastRun' => \App\Models\AnalysisOutput::latest_of('run_summary')?->generated_at,
        ]);
    }

    public function decide(Request $request, Rec $rec, ProcurementService $svc)
    {
        $d = $request->validate(['status' => ['required', 'in:approved,deferred,rejected'], 'note' => ['nullable', 'string', 'max:300']]);
        try {
            $svc->decide($rec, $request->user(), RecStatus::from($d['status']), $d['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', ['approved' => 'Rekomendasi disetujui. Catat penambahan setelah barang datang.', 'deferred' => 'Rekomendasi ditunda.', 'rejected' => 'Rekomendasi ditolak.'][$d['status']]);
    }

    public function fulfill(Request $request, Rec $rec, ProcurementService $svc)
    {
        $qty = (int) $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:5000']])['qty'];
        try {
            $svc->fulfill($rec, $request->user(), $qty);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.procure', ['tab' => 'ful'])->with('ok', $qty ? "{$qty} unit ditambahkan ke stok." : 'Tindak lanjut dicatat.');
    }

    public function run(Request $request, AnalyticsRunner $runner)
    {
        set_time_limit(0);
        $s = $runner->run(['forecast', 'procurement'], $request->user()->id);
        if (($s['status'] ?? '') === 'ok') {
            return back()->with('ok', ($s['created_recommendations'] ?? 0) ? $s['created_recommendations'].' rekomendasi baru dibuat.' : 'Analisis diperbarui, tidak ada rekomendasi baru.');
        }

        return back()->with('error', $s['error'] ?? 'Analisis gagal.');
    }
}
