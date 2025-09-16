<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        Blade::component('simple-layout', \App\View\Components\SimpleLayout::class);
        Blade::component('app-layout', \App\View\Components\AppLayout::class);
        Blade::component('guest-layout', \App\View\Components\GuestLayout::class);
        Blade::component('dropdown', \App\View\Components\Dropdown::class);
        Blade::component('dropdown-link', \App\View\Components\DropdownLink::class);
        Blade::component('nav-link', \App\View\Components\NavLink::class);
        Blade::component('responsive-nav-link', \App\View\Components\ResponsiveNavLink::class);
        Blade::component('application-logo', \App\View\Components\ApplicationLogo::class);
    }
}
