<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_team_id', 'name', 'x', 'y', 'quota', 'price'])]
class EventSlot extends Model
{
    protected function casts(): array
    {
        return ['x' => 'integer', 'y' => 'integer', 'quota' => 'integer', 'price' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'event_team_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** Pending + paid both hold a seat; rejected frees it. */
    public function takenCount(): int
    {
        return $this->registrations()->whereIn('status', Registration::HOLDING)->count();
    }
}
