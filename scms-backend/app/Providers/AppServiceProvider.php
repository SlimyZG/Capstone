<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $defaultFrontendUrl = app()->environment('production')
                ? 'https://scms-uclm.vercel.app'
                : 'http://localhost:5173';
            $frontendUrl = rtrim((string) (config('app.frontend_url') ?: $defaultFrontendUrl), '/');
            $email = urlencode((string) $notifiable->getEmailForPasswordReset());
            $encodedToken = urlencode($token);

            return "{$frontendUrl}/reset-password?token={$encodedToken}&email={$email}";
        });
    }
}
