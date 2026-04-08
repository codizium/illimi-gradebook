<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('illimi_gradebook_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('student_id')->index();
            $table->uuid('subject_id')->index();
            $table->uuid('academic_class_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('academic_term_id')->index();
            $table->uuid('grade_scale_id')->nullable()->index();
            $table->uuid('staff_id')->nullable()->index();
            $table->decimal('assignment1', 8, 2)->default(0);
            $table->decimal('assignment2', 8, 2)->default(0);
            $table->decimal('test1', 8, 2)->default(0);
            $table->decimal('test2', 8, 2)->default(0);
            $table->decimal('exams', 8, 2)->default(0);
            $table->string('graded', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('illimi_students');
            $table->foreign('subject_id')->references('id')->on('illimi_subjects');
            $table->foreign('academic_class_id')->references('id')->on('illimi_classes');
            $table->foreign('academic_year_id')->references('id')->on('illimi_academic_years');
            $table->foreign('academic_term_id')->references('id')->on('illimi_academic_terms');
            $table->foreign('grade_scale_id')->references('id')->on('illimi_grade_scales');
            $table->foreign('staff_id')->references('id')->on('illimi_staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('illimi_gradebook_assessments');
    }
};
