<?php

namespace App\Models;

use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;

class ItemUnit extends Model
{
    protected $fillable = ['item_id', 'asset_code', 'condition', 'status', 'acquired_at'];

    protected function casts(): array
    {
        return ['status' => UnitStatus::class, 'condition' => UnitCondition::class, 'acquired_at' => 'date'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
