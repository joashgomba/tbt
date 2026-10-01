<?php

namespace App\Services\Ntb\Integrations;

use App\Models\Complaint;
use App\Models\IntegrationFeed;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationSystem;
use App\Models\IntegrationWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IntegrationSyncService
{
    public function __construct(private readonly IntegrationAuthConfigService $auth) {}

    public function runFeedSync(IntegrationFeed $feed, string $triggeredBy = 'manual'): IntegrationSyncLog
    {
        $feed->load('system');
        $started = now();

        $log = IntegrationSyncLog::query()->create([
            'integration_system_id' => $feed->integration_system_id,
            'integration_feed_id' => $feed->id,
            'direction' => $feed->direction,
            'resource_type' => $feed->resource_type,
            'status' => 'running',
            'triggered_by' => $triggeredBy,
            'started_at' => $started,
        ]);

        try {
            if ($feed->direction === 'pull') {
                $result = $this->executePull($feed);
            } else {
                $result = $this->executePushFeed($feed);
            }

            $completed = now();
            $log->update([
                'status' => $result['status'],
                'records_processed' => $result['records_processed'],
                'records_failed' => $result['records_failed'],
                'request_summary' => $result['request_summary'],
                'response_summary' => $result['response_summary'],
                'error_message' => $result['error_message'] ?? null,
                'completed_at' => $completed,
                'duration_ms' => $started->diffInMilliseconds($completed),
            ]);

            $feed->update([
                'last_run_at' => $completed,
                'last_records_processed' => $result['records_processed'],
                'last_error' => $result['status'] === 'success' ? null : ($result['error_message'] ?? 'Sync failed'),
                'last_success_at' => $result['status'] === 'success' ? $completed : $feed->last_success_at,
            ]);

            $feed->system?->update(['last_sync_at' => $completed]);
        } catch (Throwable $e) {
            $completed = now();
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => $completed,
                'duration_ms' => $started->diffInMilliseconds($completed),
            ]);
            $feed->update([
                'last_run_at' => $completed,
                'last_error' => $e->getMessage(),
            ]);
        }

        return $log->fresh(['system', 'feed']);
    }

    public function testWebhook(IntegrationWebhook $webhook): IntegrationSyncLog
    {
        $webhook->load('system');
        $started = now();
        $payload = [
            'event' => 'integration.test',
            'timestamp' => $started->toIso8601String(),
            'system_code' => $webhook->system?->system_code,
            'message' => 'NTB Portal webhook connectivity test',
        ];

        $log = IntegrationSyncLog::query()->create([
            'integration_system_id' => $webhook->integration_system_id,
            'integration_webhook_id' => $webhook->id,
            'direction' => 'push',
            'resource_type' => 'webhook',
            'status' => 'running',
            'triggered_by' => 'manual',
            'request_summary' => 'POST '.$webhook->target_url,
            'started_at' => $started,
        ]);

        try {
            $response = Http::timeout($webhook->timeout_seconds)
                ->withHeaders([
                    'X-NTB-Event' => 'integration.test',
                    'X-NTB-Delivery' => Str::uuid()->toString(),
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->target_url, $payload);

            $completed = now();
            $status = $response->successful() ? 'success' : 'failed';

            $log->update([
                'status' => $status,
                'records_processed' => 1,
                'records_failed' => $response->successful() ? 0 : 1,
                'response_summary' => 'HTTP '.$response->status().' — '.Str::limit($response->body(), 500),
                'error_message' => $response->successful() ? null : 'Webhook returned HTTP '.$response->status(),
                'completed_at' => $completed,
                'duration_ms' => $started->diffInMilliseconds($completed),
            ]);

            $webhook->update([
                'last_triggered_at' => $completed,
                'last_status_code' => $response->status(),
                'last_error' => $response->successful() ? null : 'HTTP '.$response->status(),
            ]);
        } catch (Throwable $e) {
            $completed = now();
            $log->update([
                'status' => 'failed',
                'records_failed' => 1,
                'error_message' => $e->getMessage(),
                'completed_at' => $completed,
                'duration_ms' => $started->diffInMilliseconds($completed),
            ]);
            $webhook->update(['last_error' => $e->getMessage()]);
        }

        return $log->fresh(['system', 'webhook']);
    }

    /** @return array{status: string, records_processed: int, records_failed: int, request_summary: string, response_summary: string, error_message?: string} */
    private function executePull(IntegrationFeed $feed): array
    {
        $system = $feed->system;
        $url = $feed->endpoint_url ?: $system?->base_url;

        if (! $url) {
            return [
                'status' => 'failed',
                'records_processed' => 0,
                'records_failed' => 0,
                'request_summary' => 'No endpoint URL configured',
                'response_summary' => '',
                'error_message' => 'Feed requires endpoint_url or system base_url',
            ];
        }

        $headers = array_merge(
            ['Accept' => 'application/json', 'User-Agent' => 'NTB-Portal-Integrations/1.0'],
            $feed->headers ?? []
        );

        $pending = Http::timeout(30)->withHeaders($headers);
        if ($system) {
            $pending = $this->auth->apply($pending, $system);
        }

        $response = $pending->send(strtoupper($feed->http_method), $url);

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'records_processed' => 0,
                'records_failed' => 0,
                'request_summary' => $feed->http_method.' '.$url,
                'response_summary' => 'HTTP '.$response->status(),
                'error_message' => 'Remote system returned HTTP '.$response->status(),
            ];
        }

        $body = $response->json();
        $records = is_array($body) ? (isset($body['data']) && is_array($body['data']) ? count($body['data']) : count($body)) : 0;

        return [
            'status' => 'success',
            'records_processed' => $records,
            'records_failed' => 0,
            'request_summary' => $feed->http_method.' '.$url,
            'response_summary' => "HTTP {$response->status()} — {$records} record(s) received",
        ];
    }

    /** @return array{status: string, records_processed: int, records_failed: int, request_summary: string, response_summary: string} */
    private function executePushFeed(IntegrationFeed $feed): array
    {
        $count = match ($feed->resource_type) {
            'complaints' => Complaint::query()->count(),
            'reports' => 1,
            default => 0,
        };

        return [
            'status' => 'success',
            'records_processed' => $count,
            'records_failed' => 0,
            'request_summary' => 'Prepared outbound payload for '.$feed->resource_type,
            'response_summary' => "{$count} record(s) staged for partner delivery via API/webhook",
        ];
    }

    public function recentActivity(int $limit = 20)
    {
        return IntegrationSyncLog::query()
            ->with(['system:id,system_code,name', 'feed:id,feed_code,name'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function systemHealth(IntegrationSystem $system): array
    {
        $feeds = $system->feeds()->count();
        $activeFeeds = $system->feeds()->where('is_active', true)->count();
        $failedRecently = IntegrationSyncLog::query()
            ->where('integration_system_id', $system->id)
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subDays(7))
            ->count();

        return [
            'feeds_total' => $feeds,
            'feeds_active' => $activeFeeds,
            'api_clients' => $system->apiClients()->where('is_active', true)->whereNull('revoked_at')->count(),
            'webhooks' => $system->webhooks()->where('is_active', true)->count(),
            'failed_syncs_7d' => $failedRecently,
            'health' => $failedRecently > 3 ? 'degraded' : ($activeFeeds > 0 ? 'healthy' : 'idle'),
        ];
    }
}
