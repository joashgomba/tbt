<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('id');
            $table->string('full_name')->nullable()->after('name');
            $table->string('gender', 20)->nullable()->after('email');
            $table->string('phone_number', 30)->nullable()->unique()->after('gender');
            $table->foreignId('institution_id')->nullable()->after('phone_number');
            $table->string('preferred_language', 10)->default('en')->after('institution_id');
            $table->string('status', 30)->default('active')->after('preferred_language');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'full_name',
                'gender',
                'phone_number',
                'institution_id',
                'preferred_language',
                'status',
                'last_login_at',
            ]);
        });
    }
};
