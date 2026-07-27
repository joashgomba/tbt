<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corridors', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('name_fr');
            $table->text('description_fr')->nullable()->after('description_en');
            $table->string('primary_mode', 30)->default('multimodal')->after('description_fr');
            $table->unsignedInteger('distance_km')->nullable()->after('primary_mode');
            $table->unsignedInteger('transit_time_hours')->nullable()->after('distance_km');
            $table->string('icon', 80)->nullable()->after('transit_time_hours');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('icon');
            $table->text('deactivation_reason')->nullable()->after('active');
            $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('corridors', function (Blueprint $table) {
            $table->dropColumn([
                'description_en', 'description_fr', 'primary_mode', 'distance_km',
                'transit_time_hours', 'icon', 'sort_order', 'deactivation_reason', 'deactivated_at',
            ]);
        });
    }
};
