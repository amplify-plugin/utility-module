<?php

namespace Amplify\System\Utility;

use Amplify\System\Utility\Repositories\ImportJobRepository;
use Amplify\System\Utility\Repositories\Interfaces\ImportJobInterface;
use Illuminate\Support\ServiceProvider;

class UtilityServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(EventServiceProvider::class);

        $this->app->bind(ImportJobInterface::class, ImportJobRepository::class);
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
    }
}
