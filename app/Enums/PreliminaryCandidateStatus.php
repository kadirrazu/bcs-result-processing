<?php

namespace App\Enums;

enum PreliminaryCandidateStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Withheld = 'withheld';
    case Expelled = 'expelled';
}
