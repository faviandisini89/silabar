<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanReturn extends Model
{
    protected $table = 'returns';

    public $timestamps = false;

    protected $fillable = ['loan_id', 'received_by', 'returned_at', 'late_days', 'notes'];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function details()
    {
        return $this->hasMany(ReturnDetail::class, 'return_id');
    }
}
