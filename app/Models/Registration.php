<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Two independent axes:
 *  - status: pending → accepted | rejected (is the player in?)
 *  - paid_at: null = belum lunas, set = lunas (cash players may pay after the match)
 */
#[Fillable(['event_slot_id', 'name', 'phone', 'photo_path', 'proof_path', 'payment_method_id', 'payment_type', 'status', 'paid_at', 'token'])]
#[Hidden(['phone', 'proof_path', 'token'])]
class Registration extends Model
{
    public const STATUSES = ['pending' => 'Menunggu konfirmasi', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];

    /** Statuses that hold a seat on the pitch. */
    public const HOLDING = ['pending', 'accepted'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
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

    /** Registrations waiting for this admin's confirmation, across all their communities. */
    public static function pendingFor(User $user): Builder
    {
        return static::ownedBy($user)->where('status', 'pending');
    }

    /** Accepted players who still owe money (e.g. paying cash after the match). */
    public static function unpaidFor(User $user): Builder
    {
        return static::ownedBy($user)->where('status', 'accepted')->whereNull('paid_at')
            ->whereHas('slot', fn ($q) => $q->where('price', '>', 0));
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
            $fresh = static::whereKey($this->id)->with('slot')->lockForUpdate()->firstOrFail();

            if ($fresh->status === 'rejected' && $status !== 'rejected') {
                $slot = EventSlot::whereKey($fresh->event_slot_id)->lockForUpdate()->firstOrFail();
                if ($slot->takenCount() >= $slot->quota) {
                    return "Posisi {$slot->name} {$slot->team->name} sudah diambil pemain lain.";
                }
            }

            $paidAt = match (true) {
                $status === 'rejected' => null,
                $status === 'accepted' && ($fresh->payment_type === 'transfer' || $fresh->slot->price === 0) => $fresh->paid_at ?? now(),
                default => $fresh->paid_at,
            };

            $fresh->update(['status' => $status, 'paid_at' => $paidAt]);
            $this->forceFill(['status' => $status, 'paid_at' => $paidAt]);

            return null;
        });
    }

    /** Mark (or unmark) payment received — independent of acceptance, works after the event too. */
    public function markPaid(bool $paid = true): void
    {
        abort_if($this->status === 'rejected', 422);
        $this->update(['paid_at' => $paid ? ($this->paid_at ?? now()) : null]);
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
