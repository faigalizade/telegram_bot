<?php

namespace App\Facades;

use App\Services\PlatformServices\PlatformService;

class PlatformFacade
{
    public static function make($platform): PlatformService
    {
        return new $platform();
    }
}
