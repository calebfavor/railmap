<?php

namespace Railroad\Railmap;

use Illuminate\Support\ServiceProvider;
use Railroad\Railmap\IdentityMap\IdentityMap;

class RailmapServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(IdentityMap::class);
    }
}
