<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Penalty;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $penalties = Penalty::with('loan')->where('user_id', $u->id)->latest()->get();
        $loans = Loan::with('details.item')->where('borrower_id', $u->id)->whereIn('status', ['returned', 'rejected', 'cancelled'])->orderByDesc('start_at')->limit(30)->get();

        return view('borrower.history', ['penalties' => $penalties, 'unpaid' => $penalties->where('status.value', 'unpaid'), 'loans' => $loans]);
    }
}
