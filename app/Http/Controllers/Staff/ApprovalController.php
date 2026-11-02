<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Services\AvailabilityService;
use App\Services\LoanService;
use DomainException;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        $loans = Loan::with(['borrower', 'details.item', 'riskScore'])->where('status', 'pending')->orderBy('created_at')->get();
        $exts = LoanExtension::with(['loan.borrower', 'loan.details.item'])->where('status', 'pending')->orderBy('created_at')->get();
        $queue = $loans->map(fn ($l) => ['key' => 'l'.$l->id, 'type' => 'Pengajuan', 'loan' => $l, 'at' => $l->created_at])
            ->concat($exts->map(fn ($e) => ['key' => 'e'.$e->id, 'type' => 'Perpanjangan', 'loan' => $e->loan, 'ext' => $e, 'at' => $e->created_at]))->values();
        $sel = $queue->firstWhere('key', $request->query('pilih')) ?? $queue->first();
        $detail = null;
        if ($sel) {
            $loan = $sel['loan'];
            $history = Loan::where('borrower_id', $loan->borrower_id)->where('status', 'returned')->get(['late_days']);
            $detail = [
                'checks' => $loan->details->groupBy('item_id')->map(function ($rows) use ($loan, $availability, $sel) {
                    $item = $rows->first()->item;
                    $from = isset($sel['ext']) ? $loan->due_at : $loan->start_at;
                    $to = isset($sel['ext']) ? $loan->due_at->copy()->addDays($sel['ext']->days) : $loan->due_at;
                    $a = $availability->availability($item, $from, $to, $loan->id);

                    return ['item' => $item, 'qty' => $rows->sum('qty'), 'a' => $a, 'ok' => $rows->sum('qty') <= $a['avail']];
                })->values(),
                'late' => $history->where('late_days', '>', 0)->count(), 'returned' => $history->count(),
                'unpaid' => $loan->borrower->unpaidAmount(), 'risk' => $loan->riskScore?->risk_probability,
                'model' => $loan->riskScore?->model_version,
            ];
        }

        return view('staff.approvals', compact('queue', 'sel', 'detail'));
    }

    public function approve(Loan $loan, LoanService $svc, Request $request)
    {
        try {
            $svc->approve($loan, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.approvals')->with('ok', 'Pengajuan disetujui dan stok dicadangkan.');
    }

    public function reject(Request $request, Loan $loan, LoanService $svc)
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Isi alasan penolakan.']);
        try {
            $svc->reject($loan, $request->user(), $d['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.approvals')->with('ok', 'Pengajuan ditolak.');
    }

    public function decideExtension(Request $request, LoanExtension $extension, LoanService $svc)
    {
        $approve = $request->input('decision') === 'approve';
        $d = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => [$approve ? 'nullable' : 'required', 'string', 'max:200']], ['note.required' => 'Isi alasan penolakan.']);
        try {
            $svc->decideExtension($extension, $request->user(), $approve, $d['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.approvals')->with('ok', $approve ? 'Perpanjangan disetujui.' : 'Perpanjangan ditolak.');
    }
}
