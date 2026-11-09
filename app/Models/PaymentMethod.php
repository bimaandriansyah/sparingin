<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider', 'account_number', 'account_name'])]
class PaymentMethod extends Model
{
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
