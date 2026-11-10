<?php

namespace App\Livewire\Admin;

use App\Models\Community;
use App\Models\Event;
use App\Support\Formation;
use App\Support\PublicImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class EventForm extends Component
{
    use WithFileUploads;

    public const MAX_TEAMS = 12;

    public Community $community;

    public ?Event $event = null;

    public string $title = '';

    public string $sport = 'minisoccer';

    public int|string $players_per_team = 7;

    public string $starts_at = '';

    public string $location = '';

    public string $maps_url = '';

    public string $description = '';

    public string $visibility = 'public';

    public int|string $gk_price = 30000;

    public int|string $player_price = 50000;

    /** Promo: flat total for booking a whole team ('' = none). */
    public int|string|null $team_price = '';

    /** Promo: group tiers, each ['min' => people, 'off' => rupiah off per person]. */
    public array $discounts = [];

    public $poster;

    /** @var list<array{id: ?int, name: string, color: string, formation: string}> */
    public array $teams = [];

    /** Sport, player count, formations and the team list are frozen once someone has registered. Not client-writable. */
    #[Locked]
    public bool $locked = false;

    public function mount(?Community $community = null, ?Event $event = null): void
    {
        if ($event?->exists) {
            $this->event = $event;
            $this->community = $event->community;
            $this->fill($event->only('title', 'sport', 'players_per_team', 'location', 'visibility'));
            $this->maps_url = (string) $event->maps_url;
            $this->description = (string) $event->description;
            $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
            $this->gk_price = (int) $event->slots()->where('name', 'GK')->value('price');
            $this->player_price = (int) $event->slots()->where('name', '!=', 'GK')->value('price');
            $this->teams = $event->teams->map->only('id', 'name', 'formation')->all();
            $this->team_price = $event->team_price ?? '';
            $this->discounts = $event->group_discounts ?? [];
            $this->locked = $event->registrations()->exists();
        } else {
            $this->community = $community;
            $this->teams = [
                ['id' => null, 'name' => 'Tim Merah', 'formation' => Formation::default(7)],
                ['id' => null, 'name' => 'Tim Putih', 'formation' => Formation::default(7)],
            ];
        }

        abort_unless($this->community->user_id === auth()->id(), 403);
    }

    /** Only a real change of sport applies its default player count (tapping the current one is a no-op). */
    public function chooseSport(string $sport): void
    {
        abort_if($this->locked || ! isset(Event::SPORTS[$sport]), 403);
        if ($sport === $this->sport) {
            return;
        }
        $this->sport = $sport;
        $this->setPlayers(Event::DEFAULT_PLAYERS[$sport]);
    }

    public function updatedPlayersPerTeam(): void
    {
        $this->setPlayers((int) $this->players_per_team);
    }

    public function stepPlayers(int $by): void
    {
        $this->setPlayers((int) $this->players_per_team + $by);
    }

    /**
     * Set the player count. A team keeps its formation while it still fits; only formations that
     * no longer add up are reset to the new count's default. (The number input re-sends its value
     * on every blur, so this must be idempotent.)
     */
    private function setPlayers(int $players): void
    {
        abort_if($this->locked, 403);
        $this->players_per_team = max(Formation::MIN_PLAYERS, min(Formation::MAX_PLAYERS, $players));
        foreach ($this->teams as $i => $team) {
            if (! Formation::valid((string) $team['formation'], $this->players_per_team)) {
                $this->teams[$i]['formation'] = Formation::default($this->players_per_team);
            }
        }
    }

    public function setFormation(int $index, string $formation): void
    {
        abort_if($this->locked, 403);
        $this->teams[$index]['formation'] = $formation;
    }

    public function addTeam(): void
    {
        abort_if($this->locked || count($this->teams) >= self::MAX_TEAMS, 403);
        $this->teams[] = [
            'id' => null,
            'name' => 'Tim '.(count($this->teams) + 1),
            'formation' => Formation::default((int) $this->players_per_team),
        ];
    }

    public function removeTeam(int $index): void
    {
        abort_if($this->locked || count($this->teams) <= 1, 403);
        unset($this->teams[$index]);
        $this->teams = array_values($this->teams);
    }

    public function save(): void
    {
        $players = (int) $this->players_per_team;
        $data = $this->validate([
            'title' => 'required|string|max:120',
            'sport' => ['required', Rule::in(array_keys(Event::SPORTS))],
            'players_per_team' => 'required|integer|min:'.Formation::MIN_PLAYERS.'|max:'.Formation::MAX_PLAYERS,
            'starts_at' => 'required|date',
            'location' => 'required|string|max:200',
            'maps_url' => 'nullable|url:http,https|max:500',
            'description' => 'nullable|string|max:2000',
            'visibility' => ['required', Rule::in(array_keys(Event::VISIBILITIES))],
            'gk_price' => 'required|integer|min:0|max:10000000',
            'player_price' => 'required|integer|min:0|max:10000000',
            'poster' => 'nullable|image|max:4096',
            'team_price' => 'nullable|integer|min:0|max:100000000',
            'discounts' => 'array|max:5',
            'discounts.*.min' => 'required|integer|distinct|min:2|max:'.$players,
            'discounts.*.off' => 'required|integer|min:1|max:10000000',
            'teams' => 'required|array|min:1|max:'.self::MAX_TEAMS,
            'teams.*.name' => 'required|string|max:30|distinct:ignore_case',
            'teams.*.formation' => ['required', 'string', function ($attribute, $value, $fail) use ($players) {
                if (! Formation::valid((string) $value, $players)) {
                    $fail('Formasi harus berupa angka per lini (mis. 3-2-1) dengan total '.($players - 1).' pemain + kiper.');
                }
            }],
        ], [
            'teams.*.name.distinct' => 'Nama tim tidak boleh sama.',
            'discounts.*.min.distinct' => 'Jumlah orang promo tidak boleh sama.',
        ], [
            'gk_price' => 'iuran kiper', 'player_price' => 'iuran pemain', 'maps_url' => 'link Maps',
            'players_per_team' => 'jumlah pemain', 'teams.*.name' => 'nama tim',
            'team_price' => 'harga paket 1 tim', 'discounts.*.min' => 'minimal orang', 'discounts.*.off' => 'potongan',
        ]);

        // Re-check on the server: registrations may have arrived since the form was opened.
        $this->locked = (bool) $this->event?->registrations()->exists();

        $event = DB::transaction(function () use ($data) {
            $event = $this->event ?? $this->community->events()->make();
            $fields = collect($data)->only('title', 'starts_at', 'location', 'maps_url', 'description', 'visibility')->all();
            $fields['team_price'] = $data['team_price'] === '' || $data['team_price'] === null ? null : (int) $data['team_price'];
            $fields['group_discounts'] = collect($data['discounts'] ?? [])
                ->map(fn ($d) => ['min' => (int) $d['min'], 'off' => (int) $d['off']])->sortBy('min')->values()->all() ?: null;
            if (! $this->locked) {
                $fields += ['sport' => $data['sport'], 'players_per_team' => $data['players_per_team']];
            }

            if ($this->poster) {
                if ($event->poster_path) {
                    Storage::disk('public')->delete($event->poster_path);
                }
                $fields['poster_path'] = PublicImage::store($this->poster, 'posters');
            }

            $event->fill($fields)->save();

            if ($this->locked) {
                // Structure is frozen: only team names and prices change.
                foreach ($this->teams as $team) {
                    $event->teams()->whereKey($team['id'])->update(['name' => $team['name']]);
                }
                $event->slots()->where('name', 'GK')->update(['price' => $data['gk_price']]);
                $event->slots()->where('name', '!=', 'GK')->update(['price' => $data['player_price']]);
            } else {
                $teams = array_map(fn ($t) => ['name' => $t['name'], 'formation' => $t['formation']], $this->teams);
                $event->rebuildTeams($teams, (int) $data['gk_price'], (int) $data['player_price']);
            }

            return $event;
        });

        $this->redirectRoute('admin.events.show', $event, navigate: true);
    }

    public function addDiscount(): void
    {
        if (count($this->discounts) < 5) {
            $this->discounts[] = ['min' => 2 + count($this->discounts), 'off' => 5000];
        }
    }

    public function removeDiscount(int $i): void
    {
        unset($this->discounts[$i]);
        $this->discounts = array_values($this->discounts);
    }

    public function render()
    {
        return view('livewire.admin.event-form')
            ->title($this->event ? 'Edit Event' : 'Buat Event');
    }
}
