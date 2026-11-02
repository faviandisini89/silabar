<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['causer_id', 'action', 'subject_type', 'subject_id', 'properties', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
