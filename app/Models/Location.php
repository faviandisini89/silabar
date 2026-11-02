<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'description'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
