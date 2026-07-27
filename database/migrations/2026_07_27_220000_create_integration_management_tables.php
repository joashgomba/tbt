<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_systems', function (Blueprint $table) {
            $table->id();
            $table->string('system_code', 40)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('name_fr')->nullable();
            $table->string('system_type', 30)->default('national');
            $table->string('direction', 20)->default('bidirectional');
            $table->string('country', 80)->nullable();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('base_url', 500)->nullable();
            $table->string('auth_method', 30)->default('api_key');
            $table->text('auth_config')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'system_type']);
        });

        Schema::create('integration_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_system_id')->constrained('integration_systems')->cascadeOnDelete();
            $table->string('feed_code', 60);
            $table->string('name');
            $table->string('direction', 10)->default('pull');
            $table->string('resource_type', 40);
            $table->string('sync_mode', 20)->default('incremental');
            $table->string('endpoint_url', 500)->nullable();
            $table->string('http_method', 10)->default('GET');
            $table->string('schedule_cron', 80)->nullable();
            $table->boolean('schedule_enabled')->default(false);
            $table->json('field_mapping')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_records_processed')->default(0);
            $table->timestamps();

            $table->unique(['integration_system_id', 'feed_code']);
            $table->index(['direction', 'is_active']);
        });

        Schema::create('integration_api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_system_id')->constrained('integration_systems')->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash');
            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->json('ip_allowlist')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['integration_system_id', 'is_active']);
        });

        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_system_id')->constrained('integration_systems')->cascadeOnDelete();
            $table->string('name');
            $table->string('target_url', 500);
            $table->string('signing_secret_hash');
            $table->json('event_types');
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['integration_system_id', 'is_active']);
        });

        Schema::create('integration_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_system_id')->constrained('integration_systems')->cascadeOnDelete();
            $table->foreignId('integration_feed_id')->nullable()->constrained('integration_feeds')->nullOnDelete();
            $table->foreignId('integration_webhook_id')->nullable()->constrained('integration_webhooks')->nullOnDelete();
            $table->string('direction', 10);
            $table->string('resource_type', 40)->nullable();
            $table->string('status', 20);
            $table->string('triggered_by', 20)->default('manual');
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->text('request_summary')->nullable();
            $table->text('response_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['integration_system_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('integration_exposed_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_code', 60)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('name_fr')->nullable();
            $table->string('http_method', 10);
            $table->string('path', 255);
            $table->string('api_version', 10)->default('v1');
            $table->string('resource_type', 40);
            $table->text('description_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->boolean('auth_required')->default(true);
            $table->json('scopes_required')->nullable();
            $table->boolean('is_published')->default(true);
            $table->string('documentation_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('integration_exposed_endpoints');
        Schema::dropIfExists('integration_webhooks');
        Schema::dropIfExists('integration_api_clients');
        Schema::dropIfExists('integration_feeds');
        Schema::dropIfExists('integration_systems');
    }
};
