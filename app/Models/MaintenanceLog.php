<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    protected $fillable = ['item_unit_id', 'reported_by', 'issue', 'status', 'cost', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'cost' => 'float'];
    }

    public function unit()
    {
        return $this->belongsTo(ItemUnit::class, 'item_unit_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
