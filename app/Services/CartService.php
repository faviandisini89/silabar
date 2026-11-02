<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/** Session-backed loan cart: items plus the time window they are requested for. */
class CartService
{
    /** @return array<int,int> item_id => qty */
    public function items(): array
    {
        return session('cart.items', []);
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function set(int $itemId, int $qty): void
    {
        $items = $this->items();
        $items[$itemId] = max(1, $qty);
        session(['cart.items' => $items]);
    }

    public function remove(int $itemId): void
    {
        $items = $this->items();
        unset($items[$itemId]);
        session(['cart.items' => $items]);
    }

    public function clear(): void
    {
        session()->forget('cart.items');
    }

    public function start(): Carbon
    {
        return Carbon::parse(session('cart.start', now()->addDay()->setTime(9, 0)));
    }

    public function end(): Carbon
    {
        return Carbon::parse(session('cart.end', $this->start()->copy()->addDay()));
    }

    public function setWindow(Carbon $start, Carbon $end): void
    {
        session(['cart.start' => $start->toIso8601String(), 'cart.end' => $end->toIso8601String()]);
    }
}
