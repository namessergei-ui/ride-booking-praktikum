<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['city', 'position'])]
class TripStop extends Model
{
    public $timestamps = false;

    public function location(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city');
    }
}
