<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\IntegrationApiClient;
use App\Models\IntegrationExposedEndpoint;
use App\Models\IntegrationFeed;
use App\Models\IntegrationSyncLog;
use App\Models\IntegrationSystem;
use App\Models\IntegrationWebhook;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Integrations\IntegrationLifecycleService;
use App\Services\Ntb\Integrations\IntegrationSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IntegrationManagementController extends Controller
{
    private const SYSTEM_TYPES = ['national', 'regional', 'bilateral', 'international', 'agency'];
    private const DIRECTIONS = ['inbound', 'outbound', 'bidirectional'];
    private const AUTH_METHODS = ['api_key', 'oauth2', 'basic', 'mtls', 'none'];
    private const RESOURCE_TYPES = ['complaints', 'institutions', 'categories', 'corridors', 'locations', 'reports', 'analytics', 'custom'];
    private const SYNC_MODES = ['full', 'incremental', 'webhook', 'realtime'];
    private const EVENT_TYPES = [
        'complaint.created', 'complaint.updated', 'complaint.closed',
        'complaint.escalated', 'report.generated', 'integration.test',
    ];
    private const SCOPES = [
        'complaints.read', 'complaints.write', 'reports.read', 'reports.export',
        'institutions.read', 'master_data.read', 'analytics.read', '*',
    ];

    public function __construct(
        private readonly IntegrationLifecycleService $lifecycle,
        private readonly IntegrationSyncService $sync,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        return response()->json([
            'summary' => [
                'systems_total' => IntegrationSystem::query()->count(),
                'systems_active' => IntegrationSystem::query()->where('is_active', true)->count(),
                'feeds_total' => IntegrationFeed::query()->count(),
                'feeds_pull' => IntegrationFeed::query()->where('direction', 'pull')->count(),
                'feeds_push' => IntegrationFeed::query()->where('direction', 'push')->count(),
                'api_clients' => IntegrationApiClient::query()->where('is_active', true)->whereNull('revoked_at')->count(),
                'webhooks' => IntegrationWebhook::query()->where('is_active', true)->count(),
                'exposed_endpoints' => IntegrationExposedEndpoint::query()->where('is_published', true)->count(),
                'syncs_24h' => IntegrationSyncLog::query()->where('started_at', '>=', now()->subDay())->count(),
                'syncs_failed_7d' => IntegrationSyncLog::query()->where('status', 'failed')->where('started_at', '>=', now()->subDays(7))->count(),
            ],
            'by_type' => IntegrationSystem::query()
                ->select('system_type', DB::raw('count(*) as count'))
                ->groupBy('system_type')->orderByDesc('count')->get(),
            'by_direction' => IntegrationSystem::query()
                ->select('direction', DB::raw('count(*) as count'))
                ->groupBy('direction')->orderByDesc('count')->get(),
            'recent_activity' => $this->sync->recentActivity(15),
            'sync_timeline' => IntegrationSyncLog::query()
                ->where('started_at', '>=', now()->subDays(14))
                ->select(DB::raw('DATE(started_at) as day'), DB::raw('count(*) as count'))
                ->groupBy('day')->orderBy('day')->get(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        return response()->json([
            'system_types' => self::SYSTEM_TYPES,
            'directions' => self::DIRECTIONS,
            'auth_methods' => self::AUTH_METHODS,
            'resource_types' => self::RESOURCE_TYPES,
            'sync_modes' => self::SYNC_MODES,
            'event_types' => self::EVENT_TYPES,
            'scopes' => self::SCOPES,
            'institutions' => Institution::query()->where('status', 'active')->orderBy('institution_name')->get(['id', 'institution_name', 'institution_code']),
            'systems' => IntegrationSystem::query()->orderBy('name')->get(['id', 'system_code', 'name', 'direction', 'is_active']),
        ]);
    }

    public function indexSystems(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $query = IntegrationSystem::query()->with('institution:id,institution_name,institution_code')
            ->withCount(['feeds', 'apiClients', 'webhooks']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('system_code', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }
        if ($request->filled('system_type')) $query->where('system_type', $request->string('system_type'));
        if ($request->filled('direction')) $query->where('direction', $request->string('direction'));
        if ($request->filled('is_active')) $query->where('is_active', $request->boolean('is_active'));

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function storeSystem(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $data = $this->validateSystem($request);
        if (empty($data['system_code'])) {
            $data['system_code'] = $this->lifecycle->generateSystemCode($data['name']);
        }
        $system = IntegrationSystem::query()->create($data);
        $this->audit->log('integration.system.created', $system, null, $system->toArray(), $request);

        return response()->json(['system' => $system->load('institution')], 201);
    }

    public function showSystem(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->with([
            'institution:id,institution_name,institution_code',
            'feeds', 'apiClients', 'webhooks',
        ])->findOrFail($id);

        return response()->json([
            'system' => $system,
            'health' => $this->sync->systemHealth($system),
            'recent_logs' => $system->syncLogs()->orderByDesc('started_at')->limit(10)->get(),
        ]);
    }

    public function updateSystem(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($id);
        $old = $system->toArray();
        $system->update($this->validateSystem($request, $system));
        $this->audit->log('integration.system.updated', $system, $old, $system->fresh()->toArray(), $request);

        return response()->json(['system' => $system->fresh('institution')]);
    }

    public function activateSystem(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($id);
        $system->update(['is_active' => true]);
        $this->audit->log('integration.system.activated', $system, null, ['is_active' => true], $request);

        return response()->json(['system' => $system]);
    }

    public function deactivateSystem(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($id);
        $system->update(['is_active' => false]);
        $this->audit->log('integration.system.deactivated', $system, null, ['is_active' => false], $request);

        return response()->json(['system' => $system]);
    }

    public function storeFeed(Request $request, int $systemId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($systemId);
        $data = $this->validateFeed($request);
        if (empty($data['feed_code'])) {
            $data['feed_code'] = $this->lifecycle->generateFeedCode($system, $data['resource_type']);
        }
        $data['integration_system_id'] = $system->id;
        $feed = IntegrationFeed::query()->create($data);
        $this->audit->log('integration.feed.created', $feed, null, $feed->toArray(), $request);

        return response()->json(['feed' => $feed], 201);
    }

    public function updateFeed(Request $request, int $feedId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $feed = IntegrationFeed::query()->findOrFail($feedId);
        $old = $feed->toArray();
        $feed->update($this->validateFeed($request, $feed));
        $this->audit->log('integration.feed.updated', $feed, $old, $feed->fresh()->toArray(), $request);

        return response()->json(['feed' => $feed]);
    }

    public function runFeed(Request $request, int $feedId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $feed = IntegrationFeed::query()->findOrFail($feedId);
        $log = $this->sync->runFeedSync($feed, 'manual');
        $this->audit->log('integration.feed.synced', $feed, null, ['log_id' => $log->id, 'status' => $log->status], $request);

        return response()->json(['log' => $log]);
    }

    public function storeApiClient(Request $request, int $systemId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($systemId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(self::SCOPES)],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ip_allowlist' => ['nullable', 'array'],
            'ip_allowlist.*' => ['string', 'max:45'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);
        $result = $this->lifecycle->storeApiClient($system, $data);
        $this->audit->log('integration.client.created', $result['client'], null, ['client_id' => $result['client']->client_id], $request);

        return response()->json([
            'client' => $result['client'],
            'client_secret' => $result['client_secret'],
            'message' => 'Store the client secret now — it will not be shown again.',
        ], 201);
    }

    public function revokeApiClient(Request $request, int $clientId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $client = IntegrationApiClient::query()->findOrFail($clientId);
        $client->update(['is_active' => false, 'revoked_at' => now()]);
        $this->audit->log('integration.client.revoked', $client, null, ['revoked_at' => now()->toIso8601String()], $request);

        return response()->json(['client' => $client]);
    }

    public function rotateApiClientSecret(Request $request, int $clientId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $client = IntegrationApiClient::query()->findOrFail($clientId);
        $secret = $this->lifecycle->rotateApiClientSecret($client);
        $this->audit->log('integration.client.rotated', $client, null, ['client_id' => $client->client_id], $request);

        return response()->json(['client' => $client->fresh(), 'client_secret' => $secret]);
    }

    public function storeWebhook(Request $request, int $systemId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $system = IntegrationSystem::query()->findOrFail($systemId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_url' => ['required', 'url', 'max:500'],
            'event_types' => ['required', 'array', 'min:1'],
            'event_types.*' => ['string', Rule::in(self::EVENT_TYPES)],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        $result = $this->lifecycle->storeWebhook($system, $data);
        $this->audit->log('integration.webhook.created', $result['webhook'], null, ['target_url' => $data['target_url']], $request);

        return response()->json([
            'webhook' => $result['webhook'],
            'signing_secret' => $result['signing_secret'],
        ], 201);
    }

    public function updateWebhook(Request $request, int $webhookId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $webhook = IntegrationWebhook::query()->findOrFail($webhookId);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'target_url' => ['sometimes', 'url', 'max:500'],
            'event_types' => ['sometimes', 'array', 'min:1'],
            'event_types.*' => ['string', Rule::in(self::EVENT_TYPES)],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        $webhook->update($data);

        return response()->json(['webhook' => $webhook->fresh()]);
    }

    public function testWebhook(Request $request, int $webhookId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);
        $webhook = IntegrationWebhook::query()->findOrFail($webhookId);
        $log = $this->sync->testWebhook($webhook);

        return response()->json(['log' => $log]);
    }

    public function exposedEndpoints(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        return response()->json([
            'endpoints' => IntegrationExposedEndpoint::query()
                ->orderBy('sort_order')->orderBy('path')->get(),
            'base_url' => url('/api/v1/partner'),
        ]);
    }

    public function syncLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $query = IntegrationSyncLog::query()->with([
            'system:id,system_code,name',
            'feed:id,feed_code,name',
        ])->orderByDesc('started_at');

        if ($request->filled('system_id')) $query->where('integration_system_id', $request->integer('system_id'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('direction')) $query->where('direction', $request->string('direction'));

        $paginator = $query->paginate(min(max($request->integer('per_page', 30), 10), 100));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function auditLogs(Request $request, int $systemId): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $logs = AuditLog::query()->with('user:id,name,email')
            ->where('entity_type', IntegrationSystem::class)
            ->where('entity_id', $systemId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['logs' => $logs]);
    }

    private function validateSystem(Request $request, ?IntegrationSystem $existing = null): array
    {
        return $request->validate([
            'system_code' => ['nullable', 'string', 'max:40', Rule::unique('integration_systems', 'system_code')->ignore($existing?->id)],
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'name_fr' => ['nullable', 'string', 'max:200'],
            'system_type' => ['nullable', 'string', Rule::in(self::SYSTEM_TYPES)],
            'direction' => ['nullable', 'string', Rule::in(self::DIRECTIONS)],
            'country' => ['nullable', 'string', 'max:80'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'auth_method' => ['nullable', 'string', Rule::in(self::AUTH_METHODS)],
            'auth_config' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_fr' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateFeed(Request $request, ?IntegrationFeed $existing = null): array
    {
        return $request->validate([
            'feed_code' => ['nullable', 'string', 'max:60'],
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:200'],
            'direction' => ['nullable', 'string', Rule::in(['pull', 'push'])],
            'resource_type' => [$existing ? 'sometimes' : 'required', 'string', Rule::in(self::RESOURCE_TYPES)],
            'sync_mode' => ['nullable', 'string', Rule::in(self::SYNC_MODES)],
            'endpoint_url' => ['nullable', 'url', 'max:500'],
            'http_method' => ['nullable', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'schedule_cron' => ['nullable', 'string', 'max:80'],
            'schedule_enabled' => ['boolean'],
            'field_mapping' => ['nullable', 'array'],
            'headers' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }
}
