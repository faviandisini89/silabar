<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\ProcurementRecommendation;
use App\Models\User;

/** Angka badge di sidebar. Dihitung langsung di layout, jadi tidak butuh provider. */
class Sidebar
{
    /** @return array<string,int> nama rute => jumlah */
    public static function badges(User $u): array
    {
        $b = [];
        if ($u->isStaff()) {
            $b['staff.approvals'] = Loan::where('status', 'pending')->count() + LoanExtension::where('status', 'pending')->count();
            $b['staff.counter'] = Loan::where('status', 'overdue')->count();
        }
        if ($u->isAdmin()) {
            $b['admin.procure'] = ProcurementRecommendation::where('status', 'open')->count();
        }
        if (! $u->isStaff()) {
            $b['loans.index'] = Loan::where('borrower_id', $u->id)->where('status', 'approved')->count();
            $b['history'] = $u->penalties()->where('status', 'unpaid')->count();
        }

        return $b;
    }
}