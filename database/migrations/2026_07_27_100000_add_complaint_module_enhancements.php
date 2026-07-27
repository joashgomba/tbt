<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('closed_at');
            $table->text('closure_summary')->nullable()->after('resolution_note');
            $table->boolean('notify_reporter')->default(false)->after('reporter_phone');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('label_en')->nullable();
            $table->string('label_fr')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('from_status', 100);
            $table->string('to_status', 100);
            $table->string('label_en')->nullable();
            $table->string('label_fr')->nullable();
            $table->string('required_permission', 100)->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_status', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('system_settings');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'closure_summary', 'notify_reporter']);
        });
    }
};
