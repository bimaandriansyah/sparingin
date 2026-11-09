<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title' => 'Open Play '.fake()->dayOfWeek(),
            'sport' => 'minisoccer',
            'starts_at' => now()->addDays(3)->setTime(19, 0),
            'location' => 'Lapangan '.fake()->streetName(),
            'players_per_team' => 7,
            'status' => 'open',
        ];
    }
}
