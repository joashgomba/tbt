<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_en');
            $table->string('name_fr');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('activity_sector_id')
                ->nullable()
                ->after('ntb_category_id')
                ->constrained('activity_sectors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_sector_id');
        });

        Schema::dropIfExists('activity_sectors');
    }
};
