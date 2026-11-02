<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyItemStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['stat_date', 'item_id', 'loan_count', 'qty_borrowed', 'unique_borrowers', 'avg_duration_days', 'unmet_requests'];

    protected function casts(): array
    {
        return ['stat_date' => 'date'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
