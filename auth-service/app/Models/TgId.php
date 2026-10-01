<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_ad', 'id_tg'])]
class TgId extends Model
{
    public $timestamps = false;

    public function authCode(): BelongsTo
    {
        return $this->belongsTo(AuthCode::class, 'id_ad', 'id_ad');
    }
}
