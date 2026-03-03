<?php

namespace Nativephp\MobileWidgets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array setData(array $payload)
 * @method static array reloadAll()
 * @method static array configure(array $options = [])
 * @method static array getStatus()
 * @method static array envConfiguration()
 *
 * @see \Nativephp\MobileWidgets\WidgetManager
 */
class Widget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nativephp\MobileWidgets\WidgetManager::class;
    }
}
