<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paginasi bawaan Laravel bergaya Tailwind; aplikasi ini memakai Bootstrap.
        Paginator::defaultView('partials.paginasi');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-5');
    }
}
