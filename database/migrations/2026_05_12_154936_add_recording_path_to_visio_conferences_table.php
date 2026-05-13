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
        Schema::table('visio_conferences', function (Blueprint $table) {
            $table->string('recording_path')->nullable()->after('recording_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visio_conferences', function (Blueprint $table) {
            $table->dropColumn('recording_path');
        });
    }
};
