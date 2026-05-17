<?php

namespace Sabbajohn\PulseLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class Pulse extends Facade
{
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return 'pulse';
    }
}
