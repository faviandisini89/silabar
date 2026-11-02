<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
