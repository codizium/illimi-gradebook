<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->uuid('subject_id')->nullable()->index();
            $table->uuid('academic_class_id')->nullable()->index();
            $table->uuid('academic_year_id')->nullable()->index();
            $table->uuid('academic_term_id')->nullable()->index();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status', 50)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subject_id')->references('id')->on('illimi_subjects');
            $table->foreign('academic_class_id')->references('id')->on('illimi_classes');
            $table->foreign('academic_year_id')->references('id')->on('illimi_academic_years');
            $table->foreign('academic_term_id')->references('id')->on('illimi_academic_terms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_templates');
    }
};
