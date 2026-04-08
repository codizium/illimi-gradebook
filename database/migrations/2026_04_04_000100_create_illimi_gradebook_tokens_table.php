<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('student_id')->index();
            $table->uuid('academic_class_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('academic_term_id')->index();
            $table->string('code', 10)->unique();
            $table->string('generated_by')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('illimi_students');
            $table->foreign('academic_class_id')->references('id')->on('illimi_classes');
            $table->foreign('academic_year_id')->references('id')->on('illimi_academic_years');
            $table->foreign('academic_term_id')->references('id')->on('illimi_academic_terms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_tokens');
    }
};
