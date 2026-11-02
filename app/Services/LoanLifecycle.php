<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;

/** Time-driven transitions: overdue, H-1 reminders, and auto-cancel of unclaimed tickets. */
class LoanLifecycle
{
    public function __construct(private SettingsService $settings, private Notifier $notifier)
    {
    }

    /** @return array{overdue:int,reminded:int,cancelled:int} */
    public function tick(): array
    {
        $now = now();
        $out = ['overdue' => 0, 'reminded' => 0, 'cancelled' => 0];

        Loan::where('status', LoanStatus::Borrowed->value)->where('due_at', '<', $now)->with('borrower')->each(function (Loan $l) use (&$out) {
            $l->update(['status' => LoanStatus::Overdue]);
            $this->notifier->send($l->borrower, "{$l->loan_code} melewati jatuh tempo. Segera kembalikan agar denda tidak bertambah.", 'loans.index');
            $this->notifier->toStaff("{$l->loan_code} ({$l->borrower->name}) terlambat dikembalikan.", 'staff.counter');
            $out['overdue']++;
        });

        Loan::where('status', LoanStatus::Borrowed->value)->whereNull('reminder_sent_at')
            ->where('due_at', '>', $now)->where('due_at', '<=', $now->copy()->addDay())->with('borrower')->each(function (Loan $l) use (&$out, $now) {
                $this->notifier->send($l->borrower, "Pengingat: {$l->loan_code} jatuh tempo ".$l->due_at->translatedFormat('j M, H.i').'.', 'loans.index');
                $l->update(['reminder_sent_at' => $now]);
                $out['reminded']++;
            });

        $grace = $this->settings->int('loan.grace_hours');
        Loan::where('status', LoanStatus::Approved->value)->where('start_at', '<', $now->copy()->subHours($grace))->with('borrower')->each(function (Loan $l) use (&$out) {
            $l->update(['status' => LoanStatus::Cancelled, 'cancel_note' => 'Tidak diambil melewati masa tenggang']);
            $this->notifier->send($l->borrower, "{$l->loan_code} dibatalkan otomatis karena tidak diambil.", 'loans.index');
            $out['cancelled']++;
        });

        return $out;
    }
}
