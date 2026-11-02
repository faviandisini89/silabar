<?php

namespace App\Models;

use App\Enums\RecCause;
use App\Enums\RecStatus;
use Illuminate\Database\Eloquent\Model;

class ProcurementRecommendation extends Model
{
    protected $fillable = ['item_id', 'period_start', 'period_end', 'unmet_count', 'affected_users', 'fill_rate', 'peak_shortage_qty', 'recommended_add_qty', 'cause', 'priority_score', 'status', 'reviewed_by', 'reviewed_at', 'admin_note', 'rationale', 'window_text', 'trend', 'fulfilled_at', 'fill_before', 'added_qty', 'generated_at'];

    protected function casts(): array
    {
        return [
            'cause' => RecCause::class,
            'status' => RecStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'reviewed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'generated_at' => 'datetime',
            'fill_rate' => 'float',
            'priority_score' => 'float',
            'fill_before' => 'float',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
