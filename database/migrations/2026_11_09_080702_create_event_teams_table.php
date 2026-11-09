<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teams become rows (name, jersey color, formation) instead of a fixed count with A/B/C/D names,
 * and each event gets a players-per-team number. Existing events/slots are converted in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7); // jersey hex, e.g. #dc2626
            $table->string('formation'); // outfield lines from defence to attack, e.g. "3-2-1"
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('players_per_team')->default(7)->after('sport');
        });

        Schema::table('event_slots', function (Blueprint $table) {
            $table->foreignId('event_team_id')->nullable()->after('event_id')->constrained('event_teams')->cascadeOnDelete();
        });

        // Convert existing data.
        $old = [
            'futsal' => ['futsal', 5, '1-2-1'],
            'minisoccer' => ['minisoccer', 7, '3-2-1'],
            'fun_football' => ['sepak_bola', 11, '4-4-2'],
        ];
        $names = ['Tim A', 'Tim B', 'Tim C', 'Tim D'];
        $colors = ['#dc2626', '#ffffff', '#2563eb', '#facc15'];

        foreach (DB::table('events')->get() as $event) {
            [$sport, $players, $formation] = $old[$event->sport] ?? $old['minisoccer'];
            DB::table('events')->where('id', $event->id)->update(['sport' => $sport, 'players_per_team' => $players]);

            for ($i = 0; $i < $event->teams_count; $i++) {
                $teamId = DB::table('event_teams')->insertGetId([
                    'event_id' => $event->id, 'name' => $names[$i] ?? 'Tim '.($i + 1), 'color' => $colors[$i] ?? '#9ca3af',
                    'formation' => $formation, 'position' => $i, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('event_slots')->where('event_id', $event->id)->where('team', $i)->update(['event_team_id' => $teamId]);
            }
        }

        Schema::table('event_slots', function (Blueprint $table) {
            $table->dropColumn('team');
            $table->foreignId('event_team_id')->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('teams_count');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedTinyInteger('teams_count')->default(2);
        });
        Schema::table('event_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('team')->default(0);
            $table->dropConstrainedForeignId('event_team_id');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('players_per_team');
        });
        Schema::dropIfExists('event_teams');
    }
};
