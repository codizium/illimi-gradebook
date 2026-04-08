<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_student_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('student_id')->index();
            $table->uuid('academic_class_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('academic_term_id')->index();
            $table->uuid('staff_id')->nullable()->index();
            $table->json('effective_assessment')->nullable();
            $table->json('psychomotor_assessment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('illimi_students');
            $table->foreign('academic_class_id')->references('id')->on('illimi_classes');
            $table->foreign('academic_year_id')->references('id')->on('illimi_academic_years');
            $table->foreign('academic_term_id')->references('id')->on('illimi_academic_terms');
            $table->foreign('staff_id')->references('id')->on('illimi_staff');

            $table->unique([
                'student_id',
                'academic_class_id',
                'academic_year_id',
                'academic_term_id',
            ], 'illimi_gradebook_student_ratings_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_student_ratings');
    }
};
