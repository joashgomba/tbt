<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fcm_token', 512);
            $table->string('platform', 20)->default('unknown');
            $table->string('device_name', 120)->nullable();
            $table->boolean('notify_case_updates')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fcm_token']);
            $table->index(['fcm_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_devices');
    }
};
