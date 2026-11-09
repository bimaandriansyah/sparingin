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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_slot_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone'); // normalized 62xxx
            $table->string('proof_path');
            $table->string('status')->default('pending'); // pending, paid, rejected
            $table->string('token', 40)->unique();
            $table->timestamps();

            $table->unique(['event_id', 'phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
