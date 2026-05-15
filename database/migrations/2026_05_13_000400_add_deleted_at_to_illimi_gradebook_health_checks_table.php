<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('illimi_gradebook_health_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('illimi_gradebook_health_checks', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('illimi_gradebook_health_checks', function (Blueprint $table) {
            if (Schema::hasColumn('illimi_gradebook_health_checks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
