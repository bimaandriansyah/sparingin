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
        Schema::table('registrations', function (Blueprint $table) {
            // transfer = paid to a payment_method with proof; cash = pays at the venue; null = free position
            $table->string('payment_type')->nullable()->after('payment_method_id');
        });

        DB::table('registrations')->whereNotNull('payment_method_id')->update(['payment_type' => 'transfer']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
