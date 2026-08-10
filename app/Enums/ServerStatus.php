<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Pending = 'pending';
    case Online = 'online';
    case Offline = 'offline';
    case Warning = 'warning';
}
