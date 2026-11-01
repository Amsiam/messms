<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealRequestItem extends Model
{
    protected $guarded = ['id'];

    public function mealRequest(): BelongsTo
    {
        return $this->belongsTo(MealRequest::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
