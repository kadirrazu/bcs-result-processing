<?php

namespace App\Enums;

enum PreliminaryResultStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Cancelled = 'cancelled';
}
