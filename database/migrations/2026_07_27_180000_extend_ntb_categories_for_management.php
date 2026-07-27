<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ntb_categories', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('name_ar');
            $table->text('description_fr')->nullable()->after('description_en');
            $table->string('icon', 80)->nullable()->after('description_fr');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('ntb_categories', function (Blueprint $table) {
            $table->dropColumn(['description_en', 'description_fr', 'icon', 'sort_order']);
        });
    }
};
