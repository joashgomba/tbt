<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code', 50)->nullable()->unique()->after('id');
            $table->text('description_en')->nullable()->after('name_fr');
            $table->text('description_fr')->nullable()->after('description_en');
            $table->string('location_type', 40)->default('other')->after('description_fr');
            $table->string('icon', 80)->nullable()->after('location_type');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('icon');
            $table->boolean('is_active')->default(true)->after('border_post');
            $table->text('deactivation_reason')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'location_code', 'description_en', 'description_fr', 'location_type',
                'icon', 'sort_order', 'is_active', 'deactivation_reason', 'deactivated_at',
            ]);
        });
    }
};
