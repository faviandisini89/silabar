<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['loan_id', 'item_id', 'item_unit_id', 'qty'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }

    public function returnDetails()
    {
        return $this->hasMany(ReturnDetail::class);
    }

    public function returnedQty(): int
    {
        return (int) $this->returnDetails()->sum('qty_returned');
    }

    public function outstandingQty(): int
    {
        return max(0, $this->qty - $this->returnedQty());
    }
}
