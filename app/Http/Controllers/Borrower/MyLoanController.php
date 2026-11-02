<?php

namespace App\Http\Controllers\Borrower;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanService;
use DomainException;
use Illuminate\Http\Request;

class MyLoanController extends Controller
{
    public function index(Request $request)
    {
        $all = Loan::with(['details.item', 'pendingExtension'])->where('borrower_id', $request->user()->id)->orderByDesc('created_at')->get();
        $tabs = [
            'all' => ['Semua', $all],
            'active' => ['Aktif', $all->whereIn('status', LoanStatus::active())],
            'done' => ['Selesai', $all->where('status', LoanStatus::Returned)],
            'no' => ['Ditolak atau batal', $all->whereIn('status', [LoanStatus::Rejected, LoanStatus::Cancelled])],
        ];
        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'all';

        return view('borrower.loans', ['tabs' => $tabs, 'tab' => $tab, 'loans' => $tabs[$tab][1]->take(40)]);
    }

    public function cancel(Request $request, Loan $loan, LoanService $svc)
    {
        $this->authorize('cancel', $loan);
        try {
            $svc->cancel($loan, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Pengajuan dibatalkan.');
    }

    public function extend(Request $request, Loan $loan, LoanService $svc)
    {
        $this->authorize('extend', $loan);
        $d = $request->validate(['days' => ['required', 'integer', 'between:1,3'], 'reason' => ['required', 'string', 'max:300']], ['reason.required' => 'Tulis alasan perpanjangan.']);
        try {
            $svc->requestExtension($loan, $request->user(), (int) $d['days'], $d['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Permintaan perpanjangan dikirim ke Petugas.');
    }
}
