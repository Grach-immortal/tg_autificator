<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id_tg', 'in_group', 'is_bot'])]
class GroupMember extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'in_group' => 'boolean',
            'is_bot' => 'boolean',
        ];
    }
}
