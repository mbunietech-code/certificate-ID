<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Counter rows; only touched by App\Services\Documents\NumberGenerator under a row lock. */
class NumberSequence extends Model
{
    protected $guarded = ['*'];
}
