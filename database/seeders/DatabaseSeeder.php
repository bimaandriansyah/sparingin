<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Demo admin: log in with 081111111111 / password (UserFactory default). */
    public function run(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Demo', 'phone' => '6281111111111']);

        $community = $admin->communities()->create([
            'name' => 'Minisoccer Jaksel', 'slug' => 'minisoccer-jaksel', 'city' => 'Jakarta Selatan',
            'description' => 'Open play mini soccer tiap minggu. Semua level boleh ikut!',
            'whatsapp' => '6281234567890',
        ]);
        $community->paymentMethods()->create(['provider' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Admin Demo']);
        $community->paymentMethods()->create(['provider' => 'DANA', 'account_number' => '081234567890', 'account_name' => 'Admin Demo']);

        $event = $community->events()->create([
            'title' => 'Open Play Jumat Malam', 'sport' => 'minisoccer', 'players_per_team' => 7,
            'starts_at' => now()->next('Friday')->setTime(20, 0), 'location' => 'Lapangan Cilandak, Lap. 2',
            'maps_url' => 'https://maps.google.com/?q=Lapangan+Cilandak',
            'description' => "👕 Jersey disediakan (gelap & terang)\n🗣️ Wasit\n📸 Fotografer\n🥤 Air mineral",
        ]);
        $event->rebuildTeams([
            ['name' => 'Tim Merah', 'formation' => '3-2-1'],
            ['name' => 'Tim Putih', 'formation' => '2-3-1'],
        ], 30000, 50000);

        $past = $community->events()->create([
            'title' => 'Fun Match Minggu Lalu', 'sport' => 'futsal', 'players_per_team' => 5,
            'starts_at' => now()->subWeek()->setTime(16, 0), 'location' => 'GOR Bulungan',
        ]);
        $past->rebuildTeams([
            ['name' => 'Tim Hitam', 'formation' => '1-2-1'],
            ['name' => 'Tim Kuning', 'formation' => '2-2'],
        ], 25000, 40000);
    }
}
