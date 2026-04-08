<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('template_id')->index();
            $table->string('label');
            $table->string('code', 100)->index();
            $table->string('component_type', 50)->default('continuous_assessment')->index();
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('position')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('affects_total')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')->references('id')->on('illimi_gradebook_templates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_template_items');
    }
};
