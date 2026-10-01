<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('token_hash', 64)->unique();
            $table->string('abilities', 255)->default('mobile:complaints');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('ussd_pin_hash')->nullable()->after('country');
        });

        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 120)->unique();
            $table->string('msisdn', 30);
            $table->string('state', 80);
            $table->json('payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('ussd_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ussd_session_id')->constrained('ussd_sessions')->cascadeOnDelete();
            $table->string('state', 80);
            $table->string('user_input', 500)->nullable();
            $table->text('system_message')->nullable();
            $table->boolean('session_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_session_events');
        Schema::dropIfExists('ussd_sessions');
        Schema::dropIfExists('mobile_api_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ussd_pin_hash');
        });
    }
};
