<?php

namespace App\Models;

use App\Enums\TrackingType;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public const TILES = ['#1F6F78', '#3F6FA3', '#5E8F63', '#B98A3E', '#8C6B8F', '#477F7A', '#9B6A55'];

    protected $fillable = ['category_id', 'location_id', 'name', 'sku', 'tracking_type', 'total_stock', 'max_loan_days', 'requires_approval', 'replacement_value', 'tile', 'photo_path', 'description'];

    protected function casts(): array
    {
        return [
            'tracking_type' => TrackingType::class,
            'requires_approval' => 'boolean',
            'replacement_value' => 'float',
            'total_stock' => 'integer',
            'max_loan_days' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function units()
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function details()
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function isUnit(): bool
    {
        return $this->tracking_type === TrackingType::Unit;
    }

    /** Everything we own that is not lost (maintenance units still count as owned). */
    public function ownedCount(): int
    {
        return $this->isUnit() ? $this->units()->where('status', '!=', UnitStatus::Lost->value)->count() : $this->total_stock;
    }

    public function tileColor(): string
    {
        return self::TILES[$this->tile % count(self::TILES)];
    }
}
