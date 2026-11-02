<?php

namespace App\Models;

use App\Enums\UnmetReason;
use Illuminate\Database\Eloquent\Model;

class UnmetDemandLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['item_id', 'user_id', 'requested_qty', 'available_qty', 'requested_start_at', 'requested_end_at', 'reason', 'util', 'maint_share', 'overdue_units', 'created_at'];

    protected function casts(): array
    {
        return ['reason' => UnmetReason::class, 'requested_start_at' => 'datetime', 'requested_end_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
