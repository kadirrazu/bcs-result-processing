<?php

namespace App\Enums;

enum WrittenCandidateStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Withheld = 'withheld';
}
