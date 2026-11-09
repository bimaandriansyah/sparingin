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
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('phone'); // optional player photo, public disk
            $table->foreignId('payment_method_id')->nullable()->after('proof_path')->constrained()->nullOnDelete();
            $table->string('proof_path')->nullable()->change(); // free positions need no proof
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('photo_path');
        });
    }
};
