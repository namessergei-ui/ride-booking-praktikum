<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['make', 'model', 'color', 'passenger_seats'])]
class Vehicle extends Model {}
