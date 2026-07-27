<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('category', 50);
            $table->string('title_en');
            $table->string('title_fr');
            $table->text('description_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->string('default_frequency', 30)->default('on_demand');
            $table->json('output_formats')->nullable();
            $table->json('parameters')->nullable();
            $table->json('required_permissions')->nullable();
            $table->string('confidentiality', 30)->default('internal');
            $table->json('chart_types')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('report_kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title_en');
            $table->string('title_fr');
            $table->text('description_en')->nullable();
            $table->text('description_fr')->nullable();
            $table->string('formula', 500);
            $table->string('unit', 50)->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->decimal('warning_threshold', 12, 2)->nullable();
            $table->string('frequency', 30)->default('monthly');
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('report_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $table->string('instance_code', 40)->unique();
            $table->string('status', 30)->default('queued');
            $table->json('parameters')->nullable();
            $table->json('filters')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_format', 20)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('approval_status', 30)->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained('report_definitions')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('frequency', 30);
            $table->json('parameters')->nullable();
            $table->json('recipients')->nullable();
            $table->json('channels')->nullable();
            $table->string('output_format', 20)->default('pdf');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_suspended')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('report_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('report_instance_id')->nullable()->constrained('report_instances')->nullOnDelete();
            $table->string('report_code', 20)->nullable();
            $table->string('format', 20);
            $table->json('filters')->nullable();
            $table->string('status', 30)->default('completed');
            $table->string('file_path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_logs');
        Schema::dropIfExists('report_saved_filters');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_instances');
        Schema::dropIfExists('report_kpi_definitions');
        Schema::dropIfExists('report_definitions');
    }
};
