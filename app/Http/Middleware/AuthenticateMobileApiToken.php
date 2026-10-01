<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            abort(401, 'Mobile API bearer token required.');
        }

        $plain = trim(substr($header, 7));
        if ($plain === '') {
            abort(401, 'Mobile API bearer token required.');
        }

        $token = MobileApiToken::findByPlainText($plain);
        if (! $token || ! $token->isUsable()) {
            abort(401, 'Invalid or expired mobile API token.');
        }

        $user = $token->user;
        if (! $user) {
            abort(401, 'Invalid mobile API token.');
        }

        $token->update(['last_used_at' => now()]);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile_api_token', $token);

        return $next($request);
    }
}
