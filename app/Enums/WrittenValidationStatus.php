<?php

namespace App\Enums;

enum WrittenValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Warning = 'warning';
    case Invalid = 'invalid';
    case IdentityConflict = 'identity_conflict';
}
