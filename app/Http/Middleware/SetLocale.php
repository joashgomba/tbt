<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->preferred_language
            ?? $request->header('X-Locale')
            ?? $request->query('lang')
            ?? session('locale', 'en');

        if (! in_array($locale, ['en', 'fr'], true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
