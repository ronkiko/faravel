<?php

namespace Faravel\Support\Facades;

class Logger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'logger';
    }
}
