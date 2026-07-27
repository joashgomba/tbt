<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationClient
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $clientId = $request->header('X-API-Key') ?? $request->header('X-Client-Id');
        $clientSecret = $request->header('X-API-Secret') ?? $request->header('X-Client-Secret');

        if (! $clientId || ! $clientSecret) {
            $auth = $request->header('Authorization');
            if ($auth && str_starts_with($auth, 'Bearer ')) {
                $token = substr($auth, 7);
                if (str_contains($token, ':')) {
                    [$clientId, $clientSecret] = explode(':', $token, 2);
                }
            }
        }

        if (! $clientId || ! $clientSecret) {
            abort(401, 'Integration API credentials required.');
        }

        $client = IntegrationApiClient::query()
            ->with('system:id,system_code,name,is_active')
            ->where('client_id', $clientId)
            ->first();

        if (! $client || ! $client->isUsable() || ! Hash::check($clientSecret, $client->client_secret_hash)) {
            abort(401, 'Invalid integration API credentials.');
        }

        if ($client->ip_allowlist && count($client->ip_allowlist)) {
            abort_unless(in_array($request->ip(), $client->ip_allowlist, true), 403, 'IP address not allowlisted.');
        }

        if ($scope && ! $client->hasScope($scope)) {
            abort(403, 'Insufficient API scope.');
        }

        $client->update(['last_used_at' => now()]);
        $request->attributes->set('integration_client', $client);
        $request->attributes->set('integration_system', $client->system);

        return $next($request);
    }
}
