<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    public function add(Request $request, CartService $cart)
    {
        $data = $request->validate(['item_id' => ['required', 'exists:items,id'], 'qty' => ['required', 'integer', 'min:1', 'max:500']]);
        $cart->set((int) $data['item_id'], (int) $data['qty']);

        return back()->with('ok', Item::find($data['item_id'])->name.' masuk keranjang.');
    }

    public function update(Request $request, Item $item, CartService $cart)
    {
        $cart->set($item->id, (int) $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:500']])['qty']);

        return back();
    }

    public function remove(Item $item, CartService $cart)
    {
        $cart->remove($item->id);

        return back();
    }

    public function window(Request $request, CartService $cart)
    {
        $d = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date']]);
        $cart->setWindow(Carbon::parse($d['start']), Carbon::parse($d['end']));

        return redirect()->route('catalog')->with('ok', 'Jadwal diganti.');
    }

    /** Add a suggested bundle (items often borrowed together) in one go. */
    public function bundle(Request $request, CartService $cart)
    {
        $ids = $request->validate(['items' => ['required', 'array'], 'items.*' => ['exists:items,id']])['items'];
        foreach ($ids as $id) {
            if (! isset($cart->items()[$id])) {
                $cart->set((int) $id, 1);
            }
        }

        return redirect()->route('catalog')->with('ok', 'Paket dimasukkan ke keranjang.');
    }
}
