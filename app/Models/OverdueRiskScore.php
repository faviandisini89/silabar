<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OverdueRiskScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['loan_id', 'risk_probability', 'model_version', 'scored_at'];

    protected function casts(): array
    {
        return ['risk_probability' => 'float', 'scored_at' => 'datetime'];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
