<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Error = 'error';
}
