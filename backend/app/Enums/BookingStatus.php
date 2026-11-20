<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'REQUESTED';
    case Confirmed = 'CONFIRMED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}
