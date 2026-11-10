<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Group booking: one booker takes several positions (max one team) and pays once.
 * Members are registrations pointing at the booker's row; they have no phone of their own.
 * `amount` snapshots what each position costs after promos, so later price edits don't rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('booker_id')->nullable()->after('event_slot_id')->constrained('registrations')->cascadeOnDelete();
            $table->unsignedInteger('amount')->default(0)->after('payment_type');
            $table->string('phone')->nullable()->change();
        });

        DB::table('registrations')->update([
            'amount' => DB::raw('(select price from event_slots where event_slots.id = registrations.event_slot_id)'),
        ]);

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('team_price')->nullable()->after('players_per_team'); // flat total for booking a whole team
            $table->json('group_discounts')->nullable()->after('team_price'); // [{min: 2, off: 5000}, …] per person
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['team_price', 'group_discounts']));
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booker_id');
            $table->dropColumn('amount');
        });
    }
};
