<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Penalty;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\Request;

class PenaltyController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['paid', 'all'], true) ? $request->query('tab') : 'unpaid';
        $all = Penalty::with(['user', 'loan'])->latest()->get();
        $unpaid = $all->where('status.value', 'unpaid');
        $list = match ($tab) { 'unpaid' => $unpaid, 'paid' => $all->where('status.value', '!=', 'unpaid'), default => $all };

        return view('staff.penalties', [
            'tab' => $tab, 'list' => $list->take(60), 'unpaid' => $unpaid, 'countAll' => $all->count(),
            'paid30' => $all->where('status.value', 'paid')->where('created_at', '>', now()->subDays(30))->sum('amount'),
            'rates' => app(\App\Services\SettingsService::class)->all(),
        ]);
    }

    public function paid(Penalty $penalty, AuditLogger $audit, Notifier $notifier)
    {
        $penalty->update(['status' => 'paid']);
        $notifier->send($penalty->user, 'Denda Rp'.number_format($penalty->amount, 0, ',', '.').' sudah dicatat lunas.', 'history');
        $audit->log('Denda dilunasi', '#'.$penalty->id, (string) $penalty->amount);

        return back()->with('ok', 'Denda ditandai lunas.');
    }

    public function waive(Penalty $penalty, AuditLogger $audit)
    {
        $penalty->update(['status' => 'waived']);
        $audit->log('Denda dihapuskan', '#'.$penalty->id, (string) $penalty->amount);

        return back()->with('ok', 'Denda dihapuskan.');
    }
}
