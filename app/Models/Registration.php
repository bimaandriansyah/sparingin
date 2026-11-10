<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A group booking is one booker row (phone, proof, payment) plus member rows with booker_id set.
 * Status and payment always change for the whole group; admin lists show bookers only.
 *
 * Two independent axes:
 *  - status: pending → accepted | rejected (is the player in?)
 *  - paid_at: null = belum lunas, set = lunas (cash players may pay after the match)
 */
#[Fillable(['event_slot_id', 'booker_id', 'amount', 'name', 'phone', 'photo_path', 'proof_path', 'payment_method_id', 'payment_type', 'status', 'paid_at', 'token'])]
#[Hidden(['phone', 'proof_path', 'token'])]
class Registration extends Model
{
    public const STATUSES = ['pending' => 'Menunggu konfirmasi', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];

    /** Statuses that hold a seat on the pitch. */
    public const HOLDING = ['pending', 'accepted'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'integer'];
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(self::class, 'booker_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'booker_id');
    }

    /** The booker plus everyone booked with them. */
    public function group(): Collection
    {
        return new Collection([$this, ...$this->members]);
    }

    /** Rupiah owed by the whole booking (after promos). */
    public function groupTotal(): int
    {
        return $this->amount + (int) $this->members->sum('amount');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /** "BCA", "Tunai", or null for free positions. */
    public function paymentLabel(): ?string
    {
        return $this->payment_type === 'cash' ? 'Tunai' : $this->paymentMethod?->provider;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    /** All registrations in this admin's communities. */
    public static function ownedBy(User $user): Builder
    {
        return static::whereHas('event.community', fn ($q) => $q->where('user_id', $user->id));
    }

    /** Bookings (booker rows) waiting for this admin's confirmation, across all their communities. */
    public static function pendingFor(User $user): Builder
    {
        return static::ownedBy($user)->whereNull('booker_id')->where('status', 'pending');
    }

    /** Accepted bookings that still owe money (e.g. paying cash after the match). */
    public static function unpaidFor(User $user): Builder
    {
        return static::owing(static::ownedBy($user));
    }

    /** Narrow a query to accepted, unpaid booker rows whose group costs something. */
    public static function owing(Builder|HasMany $query): Builder|HasMany
    {
        return $query->whereNull('booker_id')->where('status', 'accepted')->whereNull('paid_at')
            ->where(fn ($q) => $q->where('amount', '>', 0)->orWhereHas('members', fn ($m) => $m->where('amount', '>', 0)));
    }

    /**
     * Change status. Accepting a transfer (proof checked) or a free spot also marks it paid;
     * cash stays unpaid until markPaid(). Un-rejecting takes a seat back, which someone else may have filled.
     *
     * @return string|null error message, null on success
     */
    public function changeStatus(string $status): ?string
    {
        abort_unless(array_key_exists($status, self::STATUSES), 422);

        return DB::transaction(function () use ($status) {
            $fresh = static::whereKey($this->booker_id ?? $this->id)->lockForUpdate()->firstOrFail();
            $group = $fresh->group()->load('slot.team');

            if ($fresh->status === 'rejected' && $status !== 'rejected') {
                foreach ($group as $reg) {
                    $slot = EventSlot::whereKey($reg->event_slot_id)->lockForUpdate()->firstOrFail();
                    if ($slot->takenCount() >= $slot->quota) {
                        return "Posisi {$slot->name} {$reg->slot->team->name} sudah diambil pemain lain.";
                    }
                }
            }

            $paidAt = match (true) {
                $status === 'rejected' => null,
                $status === 'accepted' && ($fresh->payment_type === 'transfer' || $fresh->groupTotal() === 0) => $fresh->paid_at ?? now(),
                default => $fresh->paid_at,
            };

            static::whereIn('id', $group->modelKeys())->update(['status' => $status, 'paid_at' => $paidAt]);
            $this->forceFill(['status' => $status, 'paid_at' => $paidAt]);

            return null;
        });
    }

    /** Mark (or unmark) the whole booking paid — independent of acceptance, works after the event too. */
    public function markPaid(bool $paid = true): void
    {
        abort_if($this->status === 'rejected', 422);
        $paidAt = $paid ? ($this->paid_at ?? now()) : null;
        static::where('id', $this->booker_id ?? $this->id)->orWhere('booker_id', $this->booker_id ?? $this->id)->update(['paid_at' => $paidAt]);
        $this->paid_at = $paidAt;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status];
    }

    /** 0812-3456 / +62812... / 62812... → 628123456 */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
