<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('illimi_gradebook_alerts', function (Blueprint $table) {
            $table->text('resolution_note')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('illimi_gradebook_alerts', function (Blueprint $table) {
            $table->dropColumn('resolution_note');
        });
    }
};
