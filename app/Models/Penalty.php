<?php

namespace App\Models;

use App\Enums\PenaltyStatus;
use App\Enums\PenaltyType;
use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = ['return_id', 'loan_id', 'user_id', 'type', 'amount', 'status', 'note'];

    protected function casts(): array
    {
        return ['type' => PenaltyType::class, 'status' => PenaltyStatus::class, 'amount' => 'float'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
