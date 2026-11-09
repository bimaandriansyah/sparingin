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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // public URL key
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('sport'); // futsal, minisoccer, fun_football
            $table->dateTime('starts_at');
            $table->string('location');
            $table->string('maps_url')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedTinyInteger('teams_count')->default(2);
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, closed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
