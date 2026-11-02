<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\CartService;
use App\Services\LoanService;
use App\Services\RequestValidator;
use Illuminate\Http\Request;

class LoanRequestController extends Controller
{
    public function store(Request $request, CartService $cart, RequestValidator $validator, LoanService $loans)
    {
        $request->validate(['purpose' => ['required', 'string', 'max:500']], ['purpose.required' => 'Isi tujuan peminjaman dulu.']);
        $lines = $cart->items();
        if (! $lines) {
            return redirect()->route('catalog')->with('error', 'Keranjang masih kosong.');
        }
        $user = $request->user();
        $start = $cart->start();
        $end = $cart->end();
        $result = $validator->validate($user, $lines, $start, $end);

        if (! $result['ok']) {
            $logged = $validator->recordUnmet($user, $result, $start, $end) > 0;

            return view('borrower.checkout-result', [
                'result' => $result, 'loan' => null, 'logged' => $logged,
                'suggest' => $validator->suggestStart($lines, $start, $end), 'start' => $start, 'end' => $end,
                'items' => Item::whereIn('id', array_keys($lines))->get(),
            ]);
        }
        $loan = $loans->create($user, $lines, $start, $end, $request->input('purpose'));
        $cart->clear();

        return view('borrower.checkout-result', ['result' => $result, 'loan' => $loan, 'logged' => false, 'suggest' => null, 'start' => $start, 'end' => $end, 'items' => collect()]);
    }
}
