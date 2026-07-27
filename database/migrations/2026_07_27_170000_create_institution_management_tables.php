<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('institutions')->nullOnDelete();
            $table->string('integration_code', 80)->nullable()->after('institution_code');
            $table->json('operating_hours')->nullable()->after('notification_channels');
            $table->text('suspension_reason')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->timestamp('deactivated_at')->nullable()->after('suspended_at');
        });

        Schema::create('institution_corridor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('corridor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'corridor_id']);
        });

        Schema::create('institution_ntb_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ntb_category_id')->constrained('ntb_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'ntb_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_ntb_category');
        Schema::dropIfExists('institution_corridor');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn([
                'parent_id', 'integration_code', 'operating_hours',
                'suspension_reason', 'suspended_at', 'deactivated_at',
            ]);
        });
    }
};
