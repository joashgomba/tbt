<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        Mail::extend('mailtrap', function (array $config = []) {
            $token = $config['token'] ?? config('services.mailtrap.token');
            $inboxId = (string) ($config['inbox_id'] ?? config('services.mailtrap.inbox_id', 425599));
            $sandbox = filter_var($config['sandbox'] ?? config('services.mailtrap.sandbox', true), FILTER_VALIDATE_BOOLEAN);

            if (empty($token)) {
                throw new \InvalidArgumentException('MAILTRAP_API_TOKEN is not configured.');
            }

            $scheme = $sandbox ? 'mailtrap+sandbox' : 'mailtrap+api';
            $options = $sandbox ? ['inboxId' => $inboxId] : [];

            return (new MailtrapTransportFactory)->create(new Dsn(
                $scheme,
                'default',
                $token,
                null,
                null,
                $options,
            ));
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim(config('app.frontend_url', config('app.url')), '/');

            return $base.'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email);
        });
    }
}
