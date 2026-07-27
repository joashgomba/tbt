<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->string('institution_type', 100)->nullable();
            $table->string('country_code', 10)->default('DJ');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('corridors', function (Blueprint $table) {
            $table->id();
            $table->string('corridor_code', 50)->unique();
            $table->string('corridor_name');
            $table->string('name_en')->nullable();
            $table->string('name_fr')->nullable();
            $table->string('origin_country', 100);
            $table->string('destination_country', 100);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('location_name');
            $table->string('name_en')->nullable();
            $table->string('name_fr')->nullable();
            $table->string('country', 100);
            $table->string('region', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('border_post')->default(false);
            $table->foreignId('corridor_id')->nullable()->constrained('corridors')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ntb_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_en');
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ntb_categories')->nullOnDelete();
            $table->unsignedInteger('default_sla_days')->default(2);
            $table->foreignId('responsible_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complaint_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_en');
            $table->string('name_fr');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
        });
        Schema::dropIfExists('complaint_statuses');
        Schema::dropIfExists('ntb_categories');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('corridors');
        Schema::dropIfExists('institutions');
    }
};
