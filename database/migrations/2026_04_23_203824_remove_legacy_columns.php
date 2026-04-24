<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('illimi_gradebook_assessments', function (Blueprint $table) {
            $table->dropColumn(['assignment1', 'assignment2', 'test1', 'test2', 'exams', 'graded']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('illimi_gradebook_assessments', function (Blueprint $table) {
            $table->decimal('assignment1', 5, 2)->nullable();
            $table->decimal('assignment2', 5, 2)->nullable();
            $table->decimal('test1', 5, 2)->nullable();
            $table->decimal('test2', 5, 2)->nullable();
            $table->decimal('exams', 5, 2)->nullable();
            $table->string('graded')->nullable();
        });
    }
};
