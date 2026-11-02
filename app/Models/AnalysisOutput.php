<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisOutput extends Model
{
    public $timestamps = false;

    protected $fillable = ['kind', 'item_id', 'payload', 'generated_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'generated_at' => 'datetime'];
    }

    public static function latest_of(string $kind): ?self
    {
        return static::where('kind', $kind)->orderByDesc('id')->first();
    }
}
