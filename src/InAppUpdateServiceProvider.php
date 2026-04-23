<?php

declare(strict_types=1);

namespace Wilsonatb\InAppUpdate;

use Illuminate\Support\ServiceProvider;
use Wilsonatb\InAppUpdate\Commands\CopyAssetsCommand;

final class InAppUpdateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InAppUpdate::class, function () {
            return new InAppUpdate();
        });
    }

    public function boot(): void
    {
        // Register plugin hook commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
            ]);
        }
    }
}
