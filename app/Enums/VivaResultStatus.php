<?php

namespace App\Enums;

enum VivaResultStatus: string
{
    case Pending = 'pending';
    case Pass = 'pass';
    case Fail = 'fail';
    case NotApplicable = 'not_applicable';
}
