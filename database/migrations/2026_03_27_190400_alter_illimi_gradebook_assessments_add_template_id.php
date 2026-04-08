<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('illimi_gradebook_assessments', function (Blueprint $table) {
            $table->uuid('template_id')->nullable()->after('academic_term_id')->index();
            $table->foreign('template_id')->references('id')->on('illimi_gradebook_templates');
        });
    }

    public function down(): void
    {
        Schema::table('illimi_gradebook_assessments', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });
    }
};
