<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealItem extends Model
{
    protected $guarded = ['id'];

    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
