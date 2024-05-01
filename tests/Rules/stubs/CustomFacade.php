<?php declare(strict_types=1);

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Custom extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'custom';
    }
}
