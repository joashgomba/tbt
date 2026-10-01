<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->date('occurrence_date')->nullable()->after('description');
            $table->string('country_affected', 100)->nullable()->after('occurrence_date');
            $table->text('product_description')->nullable()->after('country_destination');
            $table->string('product_country_origin', 100)->nullable()->after('product_description');
            $table->string('product_value_currency', 3)->nullable()->after('product_country_origin');
            $table->decimal('product_value_amount', 15, 2)->nullable()->after('product_value_currency');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'occurrence_date',
                'country_affected',
                'product_description',
                'product_country_origin',
                'product_value_currency',
                'product_value_amount',
            ]);
        });
    }
};
