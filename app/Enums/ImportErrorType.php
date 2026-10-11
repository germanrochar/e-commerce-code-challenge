<?php

namespace App\Enums;

enum ImportErrorType: string
{
    case Validation = 'validation';
    case Duplicate = 'duplicate';
}
