<?php

namespace Database\Seeders;

use App\Models\IntegrationExposedEndpoint;
use App\Models\IntegrationFeed;
use App\Models\IntegrationSystem;
use App\Services\Ntb\Integrations\IntegrationLifecycleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NtbIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $lifecycle = app(IntegrationLifecycleService::class);

        $endpoints = [
            ['endpoint_code' => 'PARTNER_CATALOG', 'name' => 'API Catalog', 'http_method' => 'GET', 'path' => '/catalog', 'resource_type' => 'meta', 'scopes_required' => [], 'description_en' => 'List published partner API endpoints and required scopes.', 'sort_order' => 1],
            ['endpoint_code' => 'PARTNER_COMPLAINTS_LIST', 'name' => 'List complaints', 'http_method' => 'GET', 'path' => '/complaints', 'resource_type' => 'complaints', 'scopes_required' => ['complaints.read'], 'description_en' => 'Paginated NTB case registry for authorised national/regional systems.', 'sort_order' => 2],
            ['endpoint_code' => 'PARTNER_COMPLAINTS_SHOW', 'name' => 'Complaint detail', 'http_method' => 'GET', 'path' => '/complaints/{code}', 'resource_type' => 'complaints', 'scopes_required' => ['complaints.read'], 'description_en' => 'Retrieve a single case by complaint code.', 'sort_order' => 3],
            ['endpoint_code' => 'PARTNER_INSTITUTIONS', 'name' => 'Institutions', 'http_method' => 'GET', 'path' => '/institutions', 'resource_type' => 'institutions', 'scopes_required' => ['institutions.read'], 'description_en' => 'Active institution directory with integration codes.', 'sort_order' => 4],
            ['endpoint_code' => 'PARTNER_MASTER_DATA', 'name' => 'Master data', 'http_method' => 'GET', 'path' => '/master-data', 'resource_type' => 'master_data', 'scopes_required' => ['master_data.read'], 'description_en' => 'Categories, corridors and geocoded locations reference data.', 'sort_order' => 5],
            ['endpoint_code' => 'PARTNER_REPORTS_SUMMARY', 'name' => 'Reports summary', 'http_method' => 'GET', 'path' => '/reports/summary', 'resource_type' => 'reports', 'scopes_required' => ['reports.read'], 'description_en' => 'Aggregated case statistics for regional reporting dashboards.', 'sort_order' => 6],
        ];

        foreach ($endpoints as $row) {
            IntegrationExposedEndpoint::query()->updateOrCreate(
                ['endpoint_code' => $row['endpoint_code']],
                array_merge($row, ['name_en' => $row['name'], 'is_published' => true, 'auth_required' => true])
            );
        }

        $eth = IntegrationSystem::query()->updateOrCreate(
            ['system_code' => 'ETH_CUSTOMS'],
            [
                'name' => 'Ethiopia Customs Commission',
                'name_en' => 'Ethiopia Customs Commission',
                'system_type' => 'national',
                'direction' => 'bidirectional',
                'country' => 'Ethiopia',
                'base_url' => 'https://api.customs.et/v1',
                'auth_method' => 'api_key',
                'contact_name' => 'Integration Desk',
                'contact_email' => 'integration@customs.et',
                'description_en' => 'National customs system — inbound case sync and outbound status sharing.',
                'is_active' => true,
            ]
        );

        IntegrationFeed::query()->updateOrCreate(
            ['integration_system_id' => $eth->id, 'feed_code' => 'COMPLAINTS_PULL'],
            [
                'name' => 'Pull border clearance cases',
                'direction' => 'pull',
                'resource_type' => 'complaints',
                'sync_mode' => 'incremental',
                'endpoint_url' => 'https://api.customs.et/v1/ntb/cases',
                'http_method' => 'GET',
                'schedule_cron' => '0 */6 * * *',
                'schedule_enabled' => true,
                'is_active' => true,
            ]
        );

        IntegrationFeed::query()->updateOrCreate(
            ['integration_system_id' => $eth->id, 'feed_code' => 'REPORTS_PUSH'],
            [
                'name' => 'Push monthly NTB summary',
                'direction' => 'push',
                'resource_type' => 'reports',
                'sync_mode' => 'incremental',
                'schedule_cron' => '0 2 1 * *',
                'schedule_enabled' => true,
                'is_active' => true,
            ]
        );

        $igad = IntegrationSystem::query()->updateOrCreate(
            ['system_code' => 'IGAD_TRADE'],
            [
                'name' => 'IGAD Trade Facilitation Platform',
                'name_en' => 'IGAD Trade Facilitation Platform',
                'system_type' => 'regional',
                'direction' => 'outbound',
                'country' => 'Regional',
                'base_url' => 'https://trade.igad.int/api',
                'auth_method' => 'oauth2',
                'contact_email' => 'tradehub@igad.int',
                'description_en' => 'Regional reporting hub — receives NTB statistics and case milestones via partner API.',
                'is_active' => true,
            ]
        );

        if (! $igad->apiClients()->exists()) {
            $creds = $lifecycle->createApiClientCredentials();
            $igad->apiClients()->create([
                'name' => 'IGAD production client',
                'client_id' => $creds['client_id'],
                'client_secret_hash' => Hash::make($creds['client_secret']),
                'scopes' => ['complaints.read', 'reports.read', 'master_data.read'],
                'rate_limit_per_minute' => 120,
                'is_active' => true,
            ]);
        }

        if (! $igad->webhooks()->exists()) {
            $secret = $lifecycle->generateWebhookSecret();
            $igad->webhooks()->create([
                'name' => 'Case closure notifications',
                'target_url' => 'https://trade.igad.int/webhooks/ntb',
                'signing_secret_hash' => Hash::make($secret),
                'event_types' => ['complaint.closed', 'complaint.escalated'],
                'is_active' => true,
            ]);
        }

        IntegrationSystem::query()->updateOrCreate(
            ['system_code' => 'COMESA_OBS'],
            [
                'name' => 'COMESA NTB Observatory',
                'name_en' => 'COMESA NTB Observatory',
                'system_type' => 'regional',
                'direction' => 'inbound',
                'country' => 'Regional',
                'base_url' => 'https://observatory.comesa.int/api/v2',
                'auth_method' => 'api_key',
                'description_en' => 'Pull regional benchmark indicators and taxonomy updates.',
                'is_active' => true,
            ]
        );
    }
}
