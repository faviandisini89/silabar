<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnDetail extends Model
{
    protected $table = 'return_details';

    public $timestamps = false;

    protected $fillable = ['return_id', 'loan_detail_id', 'condition_after', 'qty_returned'];

    public function loanDetail()
    {
        return $this->belongsTo(LoanDetail::class);
    }
}
