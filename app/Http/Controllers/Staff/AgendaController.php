<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\MaintenanceLog;
use App\Services\AvailabilityService;

class AgendaController extends Controller
{
    public function index(AvailabilityService $availability)
    {
        $today = now()->startOfDay();
        $with = ['borrower', 'details.item'];
        $critical = Item::all()->map(function (Item $i) use ($availability) {
            $a = $availability->availability($i, now(), now()->addDay());

            return ['item' => $i, 'a' => $a, 'ratio' => $a['usable'] ? $a['avail'] / $a['usable'] : 0];
        })->sortBy('ratio')->take(7)->values();

        return view('staff.agenda', [
            'pending' => Loan::where('status', 'pending')->count() + LoanExtension::where('status', 'pending')->count(),
            'handovers' => Loan::with($with)->where('status', 'approved')->where('start_at', '<', $today->copy()->addDays(2))->orderBy('start_at')->get(),
            'dueToday' => Loan::with($with)->where('status', 'borrowed')->where('due_at', '<', $today->copy()->addDay())->orderBy('due_at')->get(),
            'overdue' => Loan::with($with)->where('status', 'overdue')->orderBy('due_at')->get(),
            'critical' => $critical,
            'repairs' => MaintenanceLog::with('unit.item')->where('status', 'open')->orderBy('started_at')->get(),
            'extensions' => LoanExtension::where('status', 'pending')->count(),
        ]);
    }
}
