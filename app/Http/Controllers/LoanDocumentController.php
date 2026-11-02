<?php

namespace App\Http\Controllers;

use App\Models\Loan;

class LoanDocumentController extends Controller
{
    /** Printable handover record (berita acara). Use the browser's "Save as PDF". */
    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);
        abort_unless($loan->handed_over_at, 404, 'Berita acara tersedia setelah serah terima.');

        return view('loan-document', ['loan' => $loan->load(['borrower', 'handedOverBy', 'details.item', 'details.unit'])]);
    }
}
