<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('user_uuid')->nullable()->unique()->after('id');
            $table->string('account_category', 50)->default('institutional')->after('status');
            $table->string('job_title')->nullable()->after('account_category');
            $table->foreignId('unit_id')->nullable()->after('institution_id');
            $table->boolean('is_focal_point')->default(false)->after('job_title');
            $table->boolean('is_institution_manager')->default(false)->after('is_focal_point');
            $table->boolean('mfa_enrolled')->default(false)->after('is_institution_manager');
            $table->boolean('password_must_change')->default(false)->after('mfa_enrolled');
            $table->text('suspension_reason')->nullable()->after('password_must_change');
            $table->timestamp('suspended_until')->nullable()->after('suspension_reason');
            $table->timestamp('deactivated_at')->nullable()->after('suspended_until');
            $table->timestamp('phone_verified_at')->nullable()->after('deactivated_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('phone_verified_at');
            $table->foreignId('created_by')->nullable()->after('terms_accepted_at');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('created_by');
            $table->timestamp('locked_at')->nullable()->after('failed_login_attempts');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->string('institution_code', 30)->nullable()->unique()->after('id');
            $table->string('name_en')->nullable()->after('institution_name');
            $table->string('name_fr')->nullable()->after('name_en');
            $table->text('mandate')->nullable()->after('address');
            $table->json('escalation_contacts')->nullable()->after('mandate');
            $table->json('notification_channels')->nullable()->after('escalation_contacts');
        });

        $rolesTable = config('permission.table_names.roles');
        $permissionsTable = config('permission.table_names.permissions');

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->string('code', 80)->nullable()->unique()->after('name');
            $table->string('title_en')->nullable()->after('code');
            $table->string('title_fr')->nullable()->after('title_en');
            $table->text('description_en')->nullable()->after('title_fr');
            $table->text('description_fr')->nullable()->after('description_en');
            $table->string('category', 50)->default('institutional')->after('description_fr');
            $table->string('scope', 50)->default('institution')->after('category');
            $table->boolean('is_active')->default(true)->after('scope');
            $table->boolean('is_protected')->default(false)->after('is_active');
            $table->unsignedSmallInteger('version')->default(1)->after('is_protected');
        });

        Schema::table($permissionsTable, function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('name');
            $table->string('title_fr')->nullable()->after('title_en');
            $table->string('resource', 80)->nullable()->after('title_fr');
            $table->string('action', 50)->nullable()->after('resource');
            $table->string('data_scope', 50)->nullable()->after('action');
            $table->text('description_en')->nullable()->after('data_scope');
            $table->text('description_fr')->nullable()->after('description_en');
        });

        Schema::create('institution_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('institution_units')->nullOnDelete();
            $table->string('unit_code', 50);
            $table->string('name_en');
            $table->string('name_fr');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['institution_id', 'unit_code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('unit_id')->references('id')->on('institution_units')->nullOnDelete();
        });

        Schema::create('user_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreign('role_id')->references('id')->on(config('permission.table_names.roles'))->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->json('permissions')->nullable();
            $table->json('scope')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('successful')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('access_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('scheduled');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_reviews');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('user_role_assignments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
        });

        Schema::dropIfExists('institution_units');

        $rolesTable = config('permission.table_names.roles');
        $permissionsTable = config('permission.table_names.permissions');

        Schema::table($permissionsTable, function (Blueprint $table) {
            $table->dropColumn(['title_en', 'title_fr', 'resource', 'action', 'data_scope', 'description_en', 'description_fr']);
        });

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->dropColumn(['code', 'title_en', 'title_fr', 'description_en', 'description_fr', 'category', 'scope', 'is_active', 'is_protected', 'version']);
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['institution_code', 'name_en', 'name_fr', 'mandate', 'escalation_contacts', 'notification_channels']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_uuid', 'account_category', 'job_title', 'unit_id', 'is_focal_point',
                'is_institution_manager', 'mfa_enrolled', 'password_must_change', 'suspension_reason',
                'suspended_until', 'deactivated_at', 'phone_verified_at', 'terms_accepted_at',
                'created_by', 'failed_login_attempts', 'locked_at',
            ]);
        });
    }
};
