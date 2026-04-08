<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_assessment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('assessment_id')->index();
            $table->uuid('template_item_id')->index();
            $table->decimal('score', 8, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('assessment_id')->references('id')->on('illimi_gradebook_assessments');
            $table->foreign('template_item_id')->references('id')->on('illimi_gradebook_template_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_assessment_items');
    }
};
