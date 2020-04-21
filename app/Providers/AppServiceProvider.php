<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //c.f.r.: https://appdividend.com/2020/03/13/laravel-7-crud-example-laravel-7-tutorial-step-by-step/
        Schema::defaultStringLength(191);
    }
}
