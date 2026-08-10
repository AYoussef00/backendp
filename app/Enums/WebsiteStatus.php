<?php

namespace App\Enums;

enum WebsiteStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Error = 'error';
    case Unknown = 'unknown';
}
