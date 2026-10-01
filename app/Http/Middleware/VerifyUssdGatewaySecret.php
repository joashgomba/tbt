<?php

namespace App\Http\Middleware;

use App\Services\Ntb\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyUssdGatewaySecret
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->settings->ussdWebhookSecret();
        if ($secret && $request->header('X-USSD-Secret') !== $secret) {
            abort(401, 'Invalid USSD gateway secret.');
        }

        return $next($request);
    }
}
