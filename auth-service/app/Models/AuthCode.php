<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_ad', 'code', 'status_employee'])]
class AuthCode extends Model
{
    public $timestamps = false;

    public function telegramIds(): HasMany
    {
        return $this->hasMany(TgId::class, 'id_ad', 'id_ad');
    }

    public function isFired(): bool
    {
        return $this->status_employee === EmployeeStatus::Fired;
    }

    protected function casts(): array
    {
        return [
            'status_employee' => EmployeeStatus::class,
        ];
    }
}
