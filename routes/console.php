<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mailtrap:test {email?}', function (?string $email = null) {
    $to = $email ?: config('mail.from.address');
    $mailer = config('mail.default');

    Mail::raw(
        'NTB Portal Mailtrap test — if you see this in your Mailtrap inbox, outbound mail is configured correctly.',
        function ($message) use ($to) {
            $message->to($to)->subject('NTB Portal — Mailtrap test');
        },
    );

    $this->info("Test message sent via mailer [{$mailer}] to {$to}. Check your Mailtrap sandbox inbox.");
})->purpose('Send a test email through the configured mailer (Mailtrap sandbox)');
