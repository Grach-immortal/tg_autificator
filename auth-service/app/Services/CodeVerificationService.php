<?php

namespace App\Services;

use App\Exceptions\EmployeeFiredException;
use App\Models\AuthCode;
use App\Models\TgId;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CodeVerificationService
{
    public function verify(string $code, string $idTg): array
    {
        $authCode = AuthCode::query()->where('code', $code)->first();

        if (! $authCode) {
            throw (new ModelNotFoundException)->setModel(AuthCode::class);
        }

        if ($authCode->isFired()) {
            throw new EmployeeFiredException('Сотрудник уволен.');
        }

        $row = TgId::query()->updateOrCreate(
            ['id_tg' => $idTg],
            ['id_ad' => $authCode->id_ad],
        );

        $authCode->code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $authCode->save();

        return [
            'id_ad' => $row->id_ad,
            'id_tg' => $row->id_tg,
        ];
    }
}
