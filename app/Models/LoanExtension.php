<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanExtension extends Model
{
    protected $fillable = ['loan_id', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'note'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
