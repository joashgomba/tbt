<?php

namespace App\Services\Ntb\Integrations;

use App\Models\IntegrationSystem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class IntegrationAuthConfigService
{
    /** @var list<string> */
    public const SECRET_KEYS = [
        'api_key', 'token', 'password', 'client_secret', 'login_password',
    ];

    /**
     * @param  array<string, mixed>|null  $incoming
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>|null
     */
    public function merge(?array $existing, ?array $incoming): ?array
    {
        if ($incoming === null) {
            return $existing;
        }

        $merged = array_merge($existing ?? [], $incoming);

        foreach (self::SECRET_KEYS as $key) {
            if (! array_key_exists($key, $incoming)) {
                continue;
            }
            $value = $incoming[$key];
            if ($value === null || $value === '' || $value === '********') {
                if (isset($existing[$key])) {
                    $merged[$key] = $existing[$key];
                } else {
                    unset($merged[$key]);
                }
            }
        }

        return $merged === [] ? null : $merged;
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    public function redact(?array $config): ?array
    {
        if (! $config) {
            return null;
        }

        $redacted = $config;
        foreach (self::SECRET_KEYS as $key) {
            if (! empty($redacted[$key])) {
                $redacted[$key] = '********';
            }
        }

        return $redacted;
    }

    public function apply(PendingRequest $request, IntegrationSystem $system): PendingRequest
    {
        $method = $system->auth_method ?? 'none';
        $config = $this->decodeConfig($system);

        return match ($method) {
            'api_key' => $this->applyApiKey($request, $config),
            'bearer' => $this->applyBearer($request, $config),
            'basic' => $this->applyBasic($request, $config),
            'oauth2' => $this->applyOAuth2($request, $system, $config),
            'login' => $this->applyLoginToken($request, $system, $config),
            'mtls' => $request,
            'none' => $request,
            default => $request,
        };
    }

    /**
     * @return array{success: bool, status_code: ?int, message: string, duration_ms: int}
     */
    public function testConnection(IntegrationSystem $system, ?string $testUrl = null): array
    {
        $url = $testUrl ?: $system->base_url;
        if (! $url) {
            return ['success' => false, 'status_code' => null, 'message' => 'Configure a base URL or test URL.', 'duration_ms' => 0];
        }

        $started = microtime(true);
        try {
            $pending = Http::timeout(25)->acceptJson()->withHeaders([
                'User-Agent' => 'NTB-Portal-Integrations/1.0',
            ]);
            $pending = $this->apply($pending, $system);
            $response = $pending->get($url);
            $duration = (int) round((microtime(true) - $started) * 1000);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'message' => $response->successful()
                    ? 'Connection successful (HTTP '.$response->status().').'
                    : 'Remote returned HTTP '.$response->status().'.',
                'duration_ms' => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $started) * 1000);

            return [
                'success' => false,
                'status_code' => null,
                'message' => $e->getMessage(),
                'duration_ms' => $duration,
            ];
        }
    }

    /** @return array<string, mixed>|null */
    public function decodeConfig(IntegrationSystem $system): ?array
    {
        $raw = $system->auth_config;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return ['token' => $raw];
        }

        return null;
    }

    /** @param  array<string, mixed>|null  $config */
    private function applyApiKey(PendingRequest $request, ?array $config): PendingRequest
    {
        $key = $config['api_key'] ?? $config['token'] ?? null;
        if (! $key) {
            return $request;
        }

        $header = $config['header_name'] ?? 'X-Api-Key';
        $prefix = $config['header_prefix'] ?? '';

        return $request->withHeaders([
            $header => $prefix ? trim($prefix.' '.$key) : $key,
        ]);
    }

    /** @param  array<string, mixed>|null  $config */
    private function applyBearer(PendingRequest $request, ?array $config): PendingRequest
    {
        $token = $config['token'] ?? $config['api_key'] ?? null;
        if (! $token) {
            return $request;
        }

        return $request->withToken($token);
    }

    /** @param  array<string, mixed>|null  $config */
    private function applyBasic(PendingRequest $request, ?array $config): PendingRequest
    {
        $user = $config['username'] ?? null;
        $pass = $config['password'] ?? null;
        if (! $user) {
            return $request;
        }

        return $request->withBasicAuth($user, (string) $pass);
    }

    /** @param  array<string, mixed>|null  $config */
    private function applyOAuth2(PendingRequest $request, IntegrationSystem $system, ?array $config): PendingRequest
    {
        $token = $this->resolveOAuth2Token($system, $config);
        if (! $token) {
            return $request;
        }

        return $request->withToken($token);
    }

    /** @param  array<string, mixed>|null  $config */
    private function applyLoginToken(PendingRequest $request, IntegrationSystem $system, ?array $config): PendingRequest
    {
        $token = $this->resolveLoginToken($system, $config);
        if (! $token) {
            return $request;
        }

        $header = $config['auth_header'] ?? 'Authorization';
        $prefix = $config['auth_prefix'] ?? 'Bearer';

        return $request->withHeaders([$header => trim($prefix.' '.$token)]);
    }

    /** @param  array<string, mixed>|null  $config */
    private function resolveOAuth2Token(IntegrationSystem $system, ?array $config): ?string
    {
        if (! $config) {
            return null;
        }

        $metadata = $system->metadata ?? [];
        if (! empty($metadata['oauth_access_token']) && ! empty($metadata['oauth_expires_at'])) {
            if (now()->lt($metadata['oauth_expires_at'])) {
                return $metadata['oauth_access_token'];
            }
        }

        $tokenUrl = $config['token_url'] ?? null;
        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;
        if (! $tokenUrl || ! $clientId || ! $clientSecret) {
            throw ValidationException::withMessages([
                'auth_config' => ['OAuth2 requires token_url, client_id, and client_secret.'],
            ]);
        }

        $grant = $config['grant_type'] ?? 'client_credentials';
        $body = [
            'grant_type' => $grant,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
        if ($grant === 'password') {
            $body['username'] = $config['username'] ?? '';
            $body['password'] = $config['password'] ?? '';
        }
        if (! empty($config['scope'])) {
            $body['scope'] = $config['scope'];
        }

        $response = Http::asForm()->timeout(20)->post($tokenUrl, $body);
        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'auth_config' => ['OAuth2 token request failed: HTTP '.$response->status()],
            ]);
        }

        $json = $response->json();
        $token = $json['access_token'] ?? null;
        if (! $token) {
            return null;
        }

        $expiresIn = (int) ($json['expires_in'] ?? 3600);
        $system->update([
            'metadata' => array_merge($metadata, [
                'oauth_access_token' => $token,
                'oauth_expires_at' => now()->addSeconds(max(60, $expiresIn - 30))->toIso8601String(),
            ]),
        ]);

        return $token;
    }

    /** @param  array<string, mixed>|null  $config */
    private function resolveLoginToken(IntegrationSystem $system, ?array $config): ?string
    {
        if (! $config) {
            return null;
        }

        $metadata = $system->metadata ?? [];
        if (! empty($metadata['login_access_token']) && ! empty($metadata['login_expires_at'])) {
            if (now()->lt($metadata['login_expires_at'])) {
                return $metadata['login_access_token'];
            }
        }

        $loginUrl = $config['login_url'] ?? null;
        if (! $loginUrl) {
            return null;
        }

        $method = strtoupper($config['login_method'] ?? 'POST');
        $payload = [
            $config['username_field'] ?? 'username' => $config['login_username'] ?? $config['username'] ?? '',
            $config['password_field'] ?? 'password' => $config['login_password'] ?? $config['password'] ?? '',
        ];

        $response = Http::timeout(20)->send($method, $loginUrl, [
            'json' => $payload,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'auth_config' => ['Login authentication failed: HTTP '.$response->status()],
            ]);
        }

        $json = $response->json();
        $path = $config['token_path'] ?? 'access_token';
        $token = data_get($json, $path);
        if (! is_string($token) || $token === '') {
            return null;
        }

        $ttl = (int) ($config['token_ttl_seconds'] ?? 3600);
        $system->update([
            'metadata' => array_merge($metadata, [
                'login_access_token' => $token,
                'login_expires_at' => now()->addSeconds(max(60, $ttl - 30))->toIso8601String(),
            ]),
        ]);

        return $token;
    }

    /** @return array<string, list<array<string, string>>> */
    public function fieldSchemas(): array
    {
        return [
            'api_key' => [
                ['key' => 'api_key', 'label' => 'API key', 'type' => 'password'],
                ['key' => 'header_name', 'label' => 'Header name', 'type' => 'text', 'default' => 'X-Api-Key'],
                ['key' => 'header_prefix', 'label' => 'Header prefix (optional)', 'type' => 'text'],
            ],
            'bearer' => [
                ['key' => 'token', 'label' => 'Bearer token', 'type' => 'password'],
            ],
            'basic' => [
                ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password'],
            ],
            'oauth2' => [
                ['key' => 'token_url', 'label' => 'Token URL', 'type' => 'url'],
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password'],
                ['key' => 'grant_type', 'label' => 'Grant type', 'type' => 'select', 'options' => 'client_credentials,password'],
                ['key' => 'username', 'label' => 'Username (password grant)', 'type' => 'text'],
                ['key' => 'password', 'label' => 'Password (password grant)', 'type' => 'password'],
                ['key' => 'scope', 'label' => 'Scope (optional)', 'type' => 'text'],
            ],
            'login' => [
                ['key' => 'login_url', 'label' => 'Login URL', 'type' => 'url'],
                ['key' => 'login_method', 'label' => 'HTTP method', 'type' => 'select', 'options' => 'POST,GET'],
                ['key' => 'login_username', 'label' => 'Username', 'type' => 'text'],
                ['key' => 'login_password', 'label' => 'Password', 'type' => 'password'],
                ['key' => 'username_field', 'label' => 'Username JSON field', 'type' => 'text', 'default' => 'username'],
                ['key' => 'password_field', 'label' => 'Password JSON field', 'type' => 'text', 'default' => 'password'],
                ['key' => 'token_path', 'label' => 'Token JSON path', 'type' => 'text', 'default' => 'access_token'],
                ['key' => 'auth_header', 'label' => 'Auth header', 'type' => 'text', 'default' => 'Authorization'],
                ['key' => 'auth_prefix', 'label' => 'Auth prefix', 'type' => 'text', 'default' => 'Bearer'],
            ],
            'none' => [],
            'mtls' => [
                ['key' => 'cert_note', 'label' => 'Certificate paths (store in secure vault / metadata)', 'type' => 'text'],
            ],
        ];
    }
}
