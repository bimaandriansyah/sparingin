<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "Accepted" (registration confirmed) and "paid" are separate: cash players may pay after the match.
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        DB::table('registrations')->where('status', 'paid')->update(['status' => 'accepted', 'paid_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('registrations')->where('status', 'accepted')->whereNotNull('paid_at')->update(['status' => 'paid']);

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
