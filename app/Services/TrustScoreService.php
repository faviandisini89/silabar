<?php

namespace App\Services;

use App\Domain\TrustScore;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;

class TrustScoreService
{
    public function refresh(User $user): int
    {
        $recent = Loan::where('borrower_id', $user->id)->where('status', 'returned')
            ->orderByDesc('returned_at')->limit(12)->pluck('late_days')->map(fn ($d) => $d > 0)->all();
        $damage = Penalty::where('user_id', $user->id)->whereIn('type', ['damaged', 'lost'])
            ->where('created_at', '>=', now()->subDays(180))->count();
        $score = TrustScore::compute($recent, $damage);
        $user->forceFill(['trust_score' => $score])->save();

        return $score;
    }
}
