<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Loan;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->role->home());
        }

        $stat = [
            'total_items' => Item::count(),
            'ready_units' => Item::all()->sum(fn ($i) => $availability->availability($i, now(), now()->addHour())['avail']),
            'running_loans' => Loan::whereIn('status', ['borrowed', 'overdue'])->count(),
            'returned_loans' => Loan::where('status', 'returned')->count(),
        ];

        $tomorrow = now()->addDay()->setTime(8, 0);

        // Ambil daftar barang beserta kategori dan ketersediaan stok
        $categoryFilter = $request->query('kategori');
        $query = Item::with(['category', 'location'])->orderBy('name');
        
        if ($categoryFilter) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categoryFilter)->orWhere('name', $categoryFilter));
        }

        $items = $query->get()->map(function ($item) use ($availability, $tomorrow) {
            $availNow = $availability->availability($item, now(), now()->addHour());
            $availTomorrow = $availability->availability($item, $tomorrow, $tomorrow->copy()->addDay());
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category?->name ?? 'Umum',
                'location' => $item->location?->name ?? 'Gudang Utama',
                'description' => $item->description,
                'total_stock' => $item->total_stock,
                'requires_approval' => $item->requires_approval,
                'max_loan_days' => $item->max_loan_days,
                'tile' => $item->tile ?? '#1F6F78',
                'avail_now' => $availNow['avail'],
                'avail_tomorrow' => $availTomorrow['avail'],
                'is_ready' => $availNow['avail'] > 0,
            ];
        });

        $categories = Category::withCount('items')->orderBy('name')->get();

        return view('welcome', compact('stat', 'items', 'categories', 'categoryFilter'));
    }
}
