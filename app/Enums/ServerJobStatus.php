<?php

namespace App\Enums;

enum ServerJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
