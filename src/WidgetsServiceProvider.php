<?php

namespace Nativephp\MobileWidgets;

use Illuminate\Support\ServiceProvider;
use Nativephp\MobileWidgets\Commands\CopyAssetsCommand;
use Nativephp\MobileWidgets\Commands\PostCompileCommand;

class WidgetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetManager::class, function () {
            return new WidgetManager();
        });

        $this->app->singleton(Widgets::class, function ($app) {
            return new Widgets($app->make(WidgetManager::class));
        });
    }

    public function boot(): void
    {
        // Register plugin hook commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
                PostCompileCommand::class,
            ]);
        }
    }
}
