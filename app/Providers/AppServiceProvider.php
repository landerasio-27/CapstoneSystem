<?php

namespace App\Providers;

use App\Support\Navigation;
use Illuminate\Support\Facades\View;
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
        // The admin layout's sidebar is composed from config/navigation.php,
        // filtered by the signed-in user's role, so every page shares one
        // definition of "what can this user open".
        View::composer('layouts.admin', function ($view) {
            $view->with('navSections', Navigation::sectionsFor(auth()->user()));
        });
    }
}
