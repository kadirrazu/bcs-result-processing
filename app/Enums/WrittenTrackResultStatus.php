<?php

namespace App\Enums;

enum WrittenTrackResultStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case NotApplicable = 'not_applicable';
}
