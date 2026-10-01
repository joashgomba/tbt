<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('phone_number');
            $table->string('country', 100)->nullable()->after('organization_name');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization_name', 'country']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
