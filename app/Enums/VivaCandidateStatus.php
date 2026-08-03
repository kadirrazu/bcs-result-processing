<?php

namespace App\Enums;

enum VivaCandidateStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Withheld = 'withheld';
    case Expelled = 'expelled';
}
