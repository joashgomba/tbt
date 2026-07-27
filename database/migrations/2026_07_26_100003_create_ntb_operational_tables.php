<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_code', 100)->unique();
            $table->string('title');
            $table->text('description');
            $table->foreignId('ntb_category_id')->constrained('ntb_categories');
            $table->foreignId('status_id')->constrained('complaint_statuses');
            $table->string('priority', 50)->default('MED');
            $table->string('country_origin', 100);
            $table->string('country_destination', 100);
            $table->foreignId('corridor_id')->constrained('corridors');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone', 30)->nullable();
            $table->foreignId('assigned_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_channel', 50)->default('WEB');
            $table->string('gender', 20)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('case_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('action_type', 100);
            $table->string('from_status', 100)->nullable();
            $table->string('to_status', 100)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('action_date');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->unsignedTinyInteger('escalation_level');
            $table->string('escalation_type', 50);
            $table->text('escalation_reason')->nullable();
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_to_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->timestamp('escalation_date');
            $table->timestamp('resolution_date')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('stage', 100);
            $table->unsignedInteger('sla_target_hours');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_breached')->default(false);
            $table->unsignedInteger('breach_hours')->nullable();
            $table->timestamp('alert_sent_at')->nullable();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('ntb_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('event_type', 100);
            $table->string('delivery_status', 30)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 100);
            $table->foreignId('ntb_category_id')->nullable()->constrained('ntb_categories')->nullOnDelete();
            $table->unsignedInteger('target_hours');
            $table->unsignedTinyInteger('alert_threshold_percent')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->unique();
            $table->string('subject_en')->nullable();
            $table->string('subject_fr')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_fr')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('sla_configurations');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ntb_notifications');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('sla_trackings');
        Schema::dropIfExists('escalations');
        Schema::dropIfExists('case_histories');
        Schema::dropIfExists('complaints');
    }
};
