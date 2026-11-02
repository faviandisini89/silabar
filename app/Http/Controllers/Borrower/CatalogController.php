<?php

namespace App\Http\Controllers\Borrower;

use App\Domain\LoanRules;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Services\AvailabilityService;
use App\Services\CartService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogController extends Controller
{
    public function index(Request $request, AvailabilityService $availability, CartService $cart, SettingsService $settings)
    {
        if ($request->filled('start') && $request->filled('end')) {
            try {
                $cart->setWindow(Carbon::parse($request->query('start')), Carbon::parse($request->query('end')));
            } catch (\Throwable $e) {
                // ignore malformed input; keep previous window
            }
        }
        $start = $cart->start();
        $end = $cart->end();
        $q = trim((string) $request->query('q'));
        $items = Item::with('category')->when($request->query('cat'), fn ($x, $c) => $x->where('category_id', $c))
            ->when($q !== '', fn ($x) => $x->where('name', 'like', "%{$q}%"))->orderBy('name')->get()
            ->map(fn (Item $i) => ['item' => $i, 'a' => $availability->availability($i, $start, $end)]);
        if ($request->boolean('only')) {
            $items = $items->filter(fn ($r) => $r['a']['avail'] > 0);
        }
        $cartItems = Item::whereIn('id', array_keys($cart->items()))->get()->keyBy('id');
        $needsApproval = $cartItems->isNotEmpty() && LoanRules::needsApproval(
            $cartItems->contains(fn ($i) => $i->requires_approval), $settings->bool('loan.auto_approve'), $request->user()->trust_score, $settings->int('loan.auto_approve_trust'),
        );

        return view('borrower.catalog', [
            'rows' => $items->values(), 'categories' => Category::orderBy('name')->get(), 'start' => $start, 'end' => $end,
            'days' => max(1, LoanRules::days($start->timestamp, $end->timestamp)), 'validWindow' => $end->gt($start),
            'cartItems' => $cartItems, 'cartQty' => $cart->items(), 'needsApproval' => $needsApproval,
            'filters' => ['q' => $q, 'cat' => $request->query('cat'), 'only' => $request->boolean('only')],
        ]);
    }

    /** JSON month grid for the availability calendar modal. */
    public function calendar(Request $request, Item $item, AvailabilityService $availability)
    {
        $offset = max(0, min(5, (int) $request->query('month', 0)));
        $month = now()->startOfMonth()->addMonths($offset);

        return response()->json([
            'title' => $month->translatedFormat('F Y'),
            'first' => $month->dayOfWeekIso - 1,
            'days_in_month' => $month->daysInMonth,
            'today' => $offset === 0 ? now()->day : null,
            'loads' => $availability->dayLoads($item, $month),
        ]);
    }
}
