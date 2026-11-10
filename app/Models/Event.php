<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\Formation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['title', 'sport', 'starts_at', 'location', 'maps_url', 'poster_path', 'players_per_team', 'team_price', 'group_discounts', 'description', 'status', 'visibility'])]
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, HasUlids;

    public const SPORTS = ['futsal' => 'Futsal', 'minisoccer' => 'Mini Soccer', 'sepak_bola' => 'Sepak Bola'];

    /** Default players per team when a sport is picked (admins can change it). */
    public const DEFAULT_PLAYERS = ['futsal' => 5, 'minisoccer' => 7, 'sepak_bola' => 11];

    public const VISIBILITIES = ['public' => 'Umum', 'internal' => 'Internal'];

    /** Mirror the DB defaults so fresh (unsaved / unrefreshed) models behave the same. */
    protected $attributes = ['status' => 'open', 'visibility' => 'public'];

    /** ULID is the public key; the integer id stays internal. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'players_per_team' => 'integer', 'team_price' => 'integer', 'group_discounts' => 'array'];
    }

    /**
     * Replace all teams and their positions. Only call while the event has no registrations.
     *
     * @param  list<array{name: string, color: string, formation: string}>  $teams
     */
    public function rebuildTeams(array $teams, int $gkPrice, int $playerPrice): void
    {
        // Deleting teams cascades to slots and registrations — refuse once anyone has registered.
        if ($this->exists && $this->registrations()->exists()) {
            throw new \LogicException('Cannot rebuild teams of an event that already has registrations.');
        }

        $this->teams()->delete(); // cascades to slots

        foreach (array_values($teams) as $i => $data) {
            $team = $this->teams()->create([...$data, 'position' => $i]);
            foreach (Formation::layout($data['formation']) as [$code, $x, $y]) {
                $this->slots()->create([
                    'event_team_id' => $team->id, 'name' => $code, 'x' => $x, 'y' => $y, 'quota' => 1,
                    'price' => $code === 'GK' ? $gkPrice : $playerPrice,
                ]);
            }
        }
    }

    /** Delete the event with its uploaded files; teams, slots and registrations cascade in the DB. */
    public function deleteWithFiles(): void
    {
        $files = $this->registrations()->get(['photo_path', 'proof_path']);
        $this->delete();
        Storage::delete($files->pluck('proof_path')->filter()->all());
        Storage::disk('public')->delete($files->pluck('photo_path')->push($this->poster_path)->filter()->all());
    }

    /** "Rp30K–50K" style label. */
    public function priceLabel(): string
    {
        $fmt = fn (int $v) => $v === 0 ? 'Gratis' : ($v % 1000 === 0 ? ($v / 1000).'K' : number_format($v, 0, ',', '.'));
        [$min, $max] = [(int) $this->slots->min('price'), (int) $this->slots->max('price')];

        return $min === $max ? $fmt($min) : $fmt($min).'–'.$fmt($max);
    }

    /** Badge text for listings. */
    public function stateLabel(): string
    {
        return match (true) {
            $this->starts_at->isPast() => 'Selesai',
            $this->status !== 'open' => 'Ditutup',
            default => 'Open',
        };
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(EventTeam::class)->orderBy('position');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<self> $query */
    public function scopePublic($query): void
    {
        $query->where('visibility', 'public');
    }

    public function isInternal(): bool
    {
        return $this->visibility === 'internal';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->starts_at->isFuture();
    }

    /** Rupiah actually received (paid_at set), whatever the payment method. */
    public function collected(): int
    {
        return (int) $this->registrations()->whereNotNull('paid_at')->where('status', 'accepted')->sum('amount');
    }

    /** Rupiah if every slot is filled and paid. */
    public function target(): int
    {
        return (int) $this->slots()->sum(DB::raw('quota * price'));
    }

    /**
     * Price a set of picked positions. Whole (empty) team + team_price → flat package;
     * otherwise the best group tier (min people) takes `off` rupiah from every position.
     *
     * @param  Collection<int, EventSlot>  $slots  booker's position first
     * @return array{amounts: array<int, int>, base: int, total: int, label: ?string}
     */
    public function quote(Collection $slots): array
    {
        $n = $slots->count();
        $base = (int) $slots->sum('price');
        $amounts = $slots->mapWithKeys(fn ($s) => [$s->id => (int) $s->price]);
        $label = null;

        $wholeTeam = $n > 1 && $slots->pluck('event_team_id')->unique()->count() === 1
            && $n === EventSlot::where('event_team_id', $slots->first()->event_team_id)->count();
        $tier = $n > 1 ? collect($this->group_discounts)->where('min', '<=', $n)->sortByDesc('min')->first() : null;

        if ($wholeTeam && $this->team_price !== null && $this->team_price < $base) {
            $share = intdiv($this->team_price, $n);
            $amounts = $slots->values()->mapWithKeys(fn ($s, $i) => [$s->id => $share + ($i === 0 ? $this->team_price - $share * $n : 0)]);
            $label = 'Paket 1 tim';
        } elseif ($tier) {
            $amounts = $amounts->map(fn ($p) => max(0, $p - (int) $tier['off']));
            $label = "Promo grup {$tier['min']}+ orang";
        }

        return ['amounts' => $amounts->all(), 'base' => $base, 'total' => (int) $amounts->sum(), 'label' => $label];
    }

    /** Single-player shortcut for book(). */
    public function register(EventSlot $slot, string $name, string $phone, ?string $proofPath, ?string $photoPath = null, ?int $paymentMethodId = null, ?string $paymentType = null): Registration
    {
        return $this->book([$slot->id => $name], $phone, $proofPath, $photoPath, $paymentMethodId, $paymentType);
    }

    /**
     * Book one or more positions (max one team's worth) for a booker and their friends.
     * Locks the slot rows so two people can't take the last seat at once.
     *
     * @param  array<int, string>  $players  slot id => player name; the first entry is the booker
     * @param  'transfer'|'cash'|null  $paymentType  null when the booking is free
     *
     * @throws ValidationException
     */
    public function book(array $players, string $phone, ?string $proofPath, ?string $photoPath = null, ?int $paymentMethodId = null, ?string $paymentType = null): Registration
    {
        $phone = Registration::normalizePhone($phone);

        if (count($players) < 1 || count($players) > $this->players_per_team) {
            throw ValidationException::withMessages(['slotIds' => "Maksimal {$this->players_per_team} posisi (1 tim) per pendaftaran."]);
        }

        return DB::transaction(function () use ($players, $phone, $proofPath, $photoPath, $paymentMethodId, $paymentType) {
            $locked = EventSlot::whereIn('id', array_keys($players))->where('event_id', $this->id)->with('team')->lockForUpdate()->get()->keyBy('id');
            if ($locked->count() !== count($players)) {
                throw ValidationException::withMessages(['slotIds' => 'Posisi tidak ditemukan, pilih ulang.']);
            }
            $slots = collect(array_keys($players))->map(fn ($id) => $locked[$id]); // keep booker first

            if (! $this->isOpen()) {
                throw ValidationException::withMessages(['slotIds' => 'Pendaftaran event ini sudah ditutup.']);
            }

            $existing = $this->registrations()->where('phone', $phone)->first();

            if ($existing && $existing->status !== 'rejected') {
                throw ValidationException::withMessages(['phone' => 'Nomor WA ini sudah terdaftar di event ini.']);
            }

            foreach ($slots as $slot) {
                if ($slot->takenCount() >= $slot->quota) {
                    throw ValidationException::withMessages(['slotIds' => "Posisi {$slot->name} {$slot->team->name} sudah diambil."]);
                }
            }

            // A rejected booker may retry: reuse the row so the (event, phone) unique key holds.
            if ($existing) {
                $existing->proof_path && Storage::delete($existing->proof_path);
                $existing->photo_path && Storage::disk('public')->delete($existing->photo_path);
                $existing->members()->delete();
            }

            $amounts = $this->quote($slots)['amounts'];
            $shared = [
                'payment_method_id' => $paymentMethodId,
                'payment_type' => $paymentType,
                'status' => 'pending', // admin confirms every booking, free ones included
            ];

            $booker = $existing ?? $this->registrations()->make(['phone' => $phone]);
            $first = $slots->first();
            $booker->fill([...$shared,
                'event_slot_id' => $first->id, 'booker_id' => null, 'name' => $players[$first->id], 'amount' => $amounts[$first->id],
                'proof_path' => $proofPath, 'photo_path' => $photoPath, 'paid_at' => null, 'token' => Str::random(40),
            ])->save();

            foreach ($slots->slice(1) as $slot) {
                $this->registrations()->create([...$shared,
                    'event_slot_id' => $slot->id, 'booker_id' => $booker->id, 'name' => $players[$slot->id], 'amount' => $amounts[$slot->id],
                    'token' => Str::random(40),
                ]);
            }

            return $booker;
        });
    }
}
