<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use App\Http\Middleware\ForceJsonResponse;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
        // Ensure API routes respond with JSON by default
        $this->app->afterResolving(Router::class, function (Router $router) {
            // Push ForceJsonResponse into the api middleware group if not already present
            try {
                $router->pushMiddlewareToGroup('api', ForceJsonResponse::class);
            } catch (\Throwable $e) {
                // safe fallback: ignore if group not available
            }
        });
    }
}
