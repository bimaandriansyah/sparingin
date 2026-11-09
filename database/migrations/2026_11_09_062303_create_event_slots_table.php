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
        Schema::create('event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('team'); // 0 = Tim A, 1 = Tim B, ...
            $table->string('name'); // position code: GK, CB, CF, ...
            $table->unsignedTinyInteger('x'); // % from left on the pitch
            $table->unsignedTinyInteger('y'); // % from top
            $table->unsignedInteger('quota');
            $table->unsignedInteger('price'); // rupiah
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_slots');
    }
};
