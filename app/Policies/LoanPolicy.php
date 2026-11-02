<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function view(User $user, Loan $loan): bool
    {
        return $user->isStaff() || $loan->borrower_id === $user->id;
    }

    public function cancel(User $user, Loan $loan): bool
    {
        return $loan->borrower_id === $user->id && in_array($loan->status, [LoanStatus::Pending, LoanStatus::Approved], true);
    }

    public function extend(User $user, Loan $loan): bool
    {
        return $loan->borrower_id === $user->id && $loan->status === LoanStatus::Borrowed;
    }
}
