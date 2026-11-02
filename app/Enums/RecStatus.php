<?php

namespace App\Enums;

enum RecStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Deferred = 'deferred';
    case Rejected = 'rejected';
    case Fulfilled = 'fulfilled';
}
