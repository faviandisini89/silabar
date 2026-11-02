<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Services\LoanService;
use DomainException;
use Illuminate\Http\Request;

class CounterController extends Controller
{
    public function index(Request $request)
    {
        // a scanned loan QR / typed code jumps straight to the right ticket
        if ($code = trim((string) $request->query('code'))) {
            $loan = Loan::where('qr_token', $code)->orWhere('loan_code', strtoupper($code))->first();
            if (! $loan) {
                return redirect()->route('staff.counter')->with('error', "Tiket {$code} tidak ditemukan.");
            }

            return redirect()->route('staff.counter', ['tab' => $loan->status->value === 'approved' ? 'handover' : 'return', 'loan' => $loan->id]);
        }
        $tab = $request->query('tab') === 'return' ? 'return' : 'handover';
        $with = ['borrower', 'details.item', 'details.unit', 'details.returnDetails'];
        $handover = Loan::with($with)->where('status', 'approved')->orderBy('start_at')->get();
        $returns = Loan::with($with)->whereIn('status', ['borrowed', 'overdue'])->orderBy('due_at')->get();
        $q = mb_strtolower(trim((string) $request->query('q')));
        $list = ($tab === 'handover' ? $handover : $returns)->filter(fn ($l) => ! $q || str_contains(mb_strtolower($l->loan_code.' '.$l->borrower->name), $q))->values();
        $sel = $list->firstWhere('id', (int) $request->query('loan'));

        return view('staff.counter', [
            'tab' => $tab, 'list' => $list, 'sel' => $sel, 'q' => $q, 'handoverCount' => $handover->count(), 'returnCount' => $returns->count(),
            'activity' => ActivityLog::with('causer')->whereIn('action', ['Serah terima barang', 'Menerima pengembalian'])->latest('id')->limit(6)->get(),
        ]);
    }

    public function handover(Request $request, Loan $loan, LoanService $svc)
    {
        $codes = collect($request->input('units', []))->map(fn ($t) => preg_split('/[\s,;]+/', (string) $t, -1, PREG_SPLIT_NO_EMPTY))->all();
        try {
            $svc->handover($loan, $request->user(), $codes, array_map('boolval', $request->input('bulk', [])));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.counter', ['tab' => 'handover'])->with('ok', "{$loan->loan_code} diserahkan. Status: Dipinjam.");
    }

    public function receive(Request $request, Loan $loan, LoanService $svc)
    {
        $rows = collect($request->input('rows', []))->map(fn ($r) => ['qty' => (int) ($r['qty'] ?? 0), 'condition' => $r['condition'] ?? 'good'])->all();
        try {
            $r = $svc->receiveReturn($loan, $request->user(), $rows, $request->input('notes'));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $msg = $r['done'] ? 'Pengembalian selesai.' : 'Pengembalian sebagian dicatat.';
        if ($r['penalty'] > 0) {
            $msg .= ' Denda dibuat: Rp'.number_format($r['penalty'], 0, ',', '.').'.';
        }

        return redirect()->route('staff.counter', ['tab' => 'return'])->with('ok', $msg);
    }
}
