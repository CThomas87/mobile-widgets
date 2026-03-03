<?php

namespace Nativephp\MobileWidgets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array execute(array $options = [])
 * @method static array getStatus()
 * @method static array setData(array $payload)
 * @method static array reloadAll()
 * @method static array configure(array $options = [])
 * @method static array envConfiguration()
 *
 * @see \Nativephp\MobileWidgets\Widgets
 */
class Widgets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nativephp\MobileWidgets\Widgets::class;
    }
}
