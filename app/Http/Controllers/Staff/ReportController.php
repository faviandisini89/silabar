<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\MaintenanceLog;
use App\Models\Penalty;
use App\Models\UnmetDemandLog;
use App\Services\AuditLogger;
use App\Services\ImportExportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function daily(): array
    {
        $d0 = now()->startOfDay();

        return [
            'Pengajuan baru' => Loan::where('created_at', '>=', $d0)->count(),
            'Disetujui' => Loan::where('approved_at', '>=', $d0)->whereNotIn('status', ['rejected'])->count(),
            'Serah terima' => Loan::where('handed_over_at', '>=', $d0)->count(),
            'Pengembalian' => Loan::where('returned_at', '>=', $d0)->count(),
            'Terlambat saat ini' => Loan::where('status', 'overdue')->count(),
            'Denda baru' => Penalty::where('created_at', '>=', $d0)->count(),
        ];
    }

    public function index()
    {
        $unmet = UnmetDemandLog::with('item')->where('created_at', '>', now()->subDays(30))->get()->groupBy('item_id')
            ->map(fn ($g) => ['name' => $g->first()->item->name, 'v' => $g->count(), 'color' => 'var(--sun)'])->sortByDesc('v')->take(6)->values()->all();

        return view('staff.reports', ['daily' => $this->daily(), 'unmet' => $unmet]);
    }

    public function dailyPrint(Request $request)
    {
        return view('staff.report-daily', [
            'daily' => $this->daily(), 'unpaid' => Penalty::where('status', 'unpaid')->sum('amount'),
            'repairs' => \App\Models\ItemUnit::where('status', 'maintenance')->count(), 'by' => $request->user(),
        ]);
    }

    public function csv(string $kind, ImportExportService $svc, AuditLogger $audit)
    {
        abort_unless(in_array($kind, ['loans', 'penalties', 'items', 'unmet'], true), 404);
        $audit->log('Ekspor data', $kind);

        return response("\xEF\xBB\xBF".$svc->export($kind), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="silabar-'.$kind.'-'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function import(Request $request, ImportExportService $svc, AuditLogger $audit)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $d = $request->validate(['csv' => ['required', 'string']], ['csv.required' => 'Tempel CSV dulu.']);
        $commit = $request->input('mode') === 'commit';
        $r = $svc->importItems($d['csv'], $commit);
        if ($commit) {
            $audit->log('Impor barang dari CSV', 'Barang', '+'.$r['count']);

            return redirect()->route('staff.reports')->with('ok', $r['count'].' barang diimpor.');
        }

        return back()->withInput()->with('ok', $r['count'].' barang siap diimpor'.($r['skipped'] ? ', '.count($r['skipped']).' baris dilewati.' : '.'));
    }
}
