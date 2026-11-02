<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ItemUnit;
use App\Models\MaintenanceLog;
use App\Services\AuditLogger;
use App\Services\LoanService;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'done' ? 'done' : 'open';
        $open = MaintenanceLog::with('unit.item')->where('status', 'open')->orderBy('started_at')->get();
        $done = MaintenanceLog::with('unit.item')->where('status', 'done')->orderByDesc('finished_at')->limit(40)->get();

        return view('staff.maintenance', [
            'tab' => $tab, 'open' => $open, 'done' => $done, 'list' => $tab === 'open' ? $open : $done,
            'available' => ItemUnit::with('item')->where('status', 'available')->orderBy('asset_code')->get(),
            'cost12m' => MaintenanceLog::where('finished_at', '>', now()->subYear())->sum('cost'),
            'inRepair' => ItemUnit::where('status', 'maintenance')->count(),
        ]);
    }

    public function store(Request $request, LoanService $loans, AuditLogger $audit)
    {
        $d = $request->validate(['unit_id' => ['required', 'exists:item_units,id'], 'issue' => ['required', 'string', 'max:300']], ['issue.required' => 'Tulis masalahnya.']);
        $unit = ItemUnit::findOrFail($d['unit_id']);
        abort_unless($unit->status->value === 'available', 422, 'Unit tidak tersedia.');
        $loans->sendToRepair($unit, $request->user(), $d['issue']);
        $audit->log('Melaporkan kerusakan', $unit->asset_code, $d['issue']);

        return back()->with('ok', 'Unit masuk perawatan.');
    }

    public function finish(Request $request, MaintenanceLog $log, AuditLogger $audit)
    {
        $cost = (float) $request->validate(['cost' => ['nullable', 'numeric', 'min:0']])['cost'];
        $log->update(['status' => 'done', 'finished_at' => now(), 'cost' => $cost]);
        $log->unit->update(['status' => 'available', 'condition' => 'good']);
        $audit->log('Menyelesaikan perawatan', $log->unit->asset_code);

        return back()->with('ok', 'Unit kembali ke stok tersedia.');
    }
}
