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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['title', 'sport', 'starts_at', 'location', 'maps_url', 'poster_path', 'players_per_team', 'description', 'status', 'visibility'])]
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
        return ['starts_at' => 'datetime', 'players_per_team' => 'integer'];
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
        return (int) $this->registrations()->whereNotNull('registrations.paid_at')->where('registrations.status', 'accepted')
            ->join('event_slots', 'event_slots.id', '=', 'registrations.event_slot_id')
            ->sum('event_slots.price');
    }

    /** Rupiah if every slot is filled and paid. */
    public function target(): int
    {
        return (int) $this->slots()->sum(DB::raw('quota * price'));
    }

    /**
     * Register a guest player. Locks the slot row so two players can't take the last seat at once.
     *
     * @throws ValidationException
     */
    /** @param  'transfer'|'cash'|null  $paymentType  null for free positions */
    public function register(EventSlot $slot, string $name, string $phone, ?string $proofPath, ?string $photoPath = null, ?int $paymentMethodId = null, ?string $paymentType = null): Registration
    {
        $phone = Registration::normalizePhone($phone);

        return DB::transaction(function () use ($slot, $name, $phone, $proofPath, $photoPath, $paymentMethodId, $paymentType) {
            $slot = EventSlot::whereKey($slot->id)->where('event_id', $this->id)->lockForUpdate()->firstOrFail();

            if (! $this->isOpen()) {
                throw ValidationException::withMessages(['slotId' => 'Pendaftaran event ini sudah ditutup.']);
            }

            $existing = $this->registrations()->where('phone', $phone)->first();

            if ($existing && $existing->status !== 'rejected') {
                throw ValidationException::withMessages(['phone' => 'Nomor WA ini sudah terdaftar di event ini.']);
            }

            if ($slot->takenCount() >= $slot->quota) {
                throw ValidationException::withMessages(['slotId' => "Posisi {$slot->name} {$slot->team->name} sudah diambil."]);
            }

            // A rejected player may retry: reuse the row so the (event, phone) unique key holds.
            if ($existing) {
                $existing->proof_path && Storage::delete($existing->proof_path);
                $existing->photo_path && Storage::disk('public')->delete($existing->photo_path);
            }

            $registration = $existing ?? $this->registrations()->make(['phone' => $phone]);
            $registration->fill([
                'event_slot_id' => $slot->id,
                'name' => $name,
                'proof_path' => $proofPath,
                'photo_path' => $photoPath,
                'payment_method_id' => $paymentMethodId,
                'payment_type' => $paymentType,
                'status' => 'pending', // admin confirms every registration, free ones included
                'token' => Str::random(40),
            ])->save();

            return $registration;
        });
    }
}
