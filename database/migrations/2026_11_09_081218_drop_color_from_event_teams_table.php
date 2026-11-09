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
        Schema::table('event_teams', function (Blueprint $table) {
            $table->dropColumn('color'); // jersey colour picker was removed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            $table->string('color', 7)->default('#ffffff')->after('name');
        });
    }
};
