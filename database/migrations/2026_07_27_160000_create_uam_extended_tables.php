<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->string('destination')->nullable();
            $table->text('secret')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'method']);
        });

        Schema::create('mfa_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->string('channel', 20)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('access_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 30);
            $table->unsignedBigInteger('new_role_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['access_review_id', 'subject_user_id']);
        });

        Schema::table('ntb_notifications', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('ntb_notifications', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });

        Schema::dropIfExists('access_review_decisions');
        Schema::dropIfExists('mfa_challenges');
        Schema::dropIfExists('mfa_methods');
    }
};
