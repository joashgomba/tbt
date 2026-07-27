<?php

namespace App\Services\Ntb\Integrations;

use App\Models\IntegrationApiClient;
use App\Models\IntegrationFeed;
use App\Models\IntegrationSystem;
use App\Models\IntegrationWebhook;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IntegrationLifecycleService
{
    public function generateSystemCode(string $name): string
    {
        $base = Str::upper(Str::slug(Str::limit($name, 20, ''), '_'));
        $code = $base ?: 'SYS';
        $i = 1;
        while (IntegrationSystem::query()->where('system_code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }

    public function generateFeedCode(IntegrationSystem $system, string $resourceType): string
    {
        $base = Str::upper($resourceType.'_'.Str::slug($system->system_code, '_'));
        $code = Str::limit($base, 50, '');
        $i = 1;
        while (IntegrationFeed::query()->where('integration_system_id', $system->id)->where('feed_code', $code)->exists()) {
            $code = Str::limit($base, 45, '').'_'.$i++;
        }

        return $code;
    }

    /** @return array{client_id: string, client_secret: string} */
    public function createApiClientCredentials(): array
    {
        return [
            'client_id' => 'ntb_'.Str::lower(Str::random(16)),
            'client_secret' => Str::random(48),
        ];
    }

    public function generateWebhookSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function storeApiClient(IntegrationSystem $system, array $data): array
    {
        $creds = $this->createApiClientCredentials();
        $client = IntegrationApiClient::query()->create([
            'integration_system_id' => $system->id,
            'name' => $data['name'],
            'client_id' => $creds['client_id'],
            'client_secret_hash' => Hash::make($creds['client_secret']),
            'scopes' => $data['scopes'] ?? [],
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'ip_allowlist' => $data['ip_allowlist'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ['client' => $client, 'client_secret' => $creds['client_secret']];
    }

    public function storeWebhook(IntegrationSystem $system, array $data): array
    {
        $secret = $this->generateWebhookSecret();
        $webhook = IntegrationWebhook::query()->create([
            'integration_system_id' => $system->id,
            'name' => $data['name'],
            'target_url' => $data['target_url'],
            'signing_secret_hash' => Hash::make($secret),
            'event_types' => $data['event_types'] ?? [],
            'max_retries' => $data['max_retries'] ?? 3,
            'timeout_seconds' => $data['timeout_seconds'] ?? 30,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ['webhook' => $webhook, 'signing_secret' => $secret];
    }

    public function rotateApiClientSecret(IntegrationApiClient $client): string
    {
        $secret = Str::random(48);
        $client->update([
            'client_secret_hash' => Hash::make($secret),
            'revoked_at' => null,
        ]);

        return $secret;
    }

    public function rotateWebhookSecret(IntegrationWebhook $webhook): string
    {
        $secret = $this->generateWebhookSecret();
        $webhook->update(['signing_secret_hash' => Hash::make($secret)]);

        return $secret;
    }
}
