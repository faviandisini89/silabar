<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandForecast extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'forecast_date', 'predicted_qty', 'lower_bound', 'upper_bound', 'model_name', 'generated_at'];

    protected function casts(): array
    {
        return ['forecast_date' => 'date', 'generated_at' => 'datetime', 'predicted_qty' => 'float', 'lower_bound' => 'float', 'upper_bound' => 'float'];
    }
}
