<?php

namespace App\Providers;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // ...
    ];

    public function boot(): void
{
    VerifyEmail::createUrlUsing(function ($notifiable) {
        $expires = Carbon::now()->addMinutes(config('auth.verification.expire', 60));

        return URL::temporarySignedRoute(
            'verification.verify',
            $expires,
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    });
}

}
