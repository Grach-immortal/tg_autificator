<?php

namespace App\Services;

use App\Models\AuthCode;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuthCodeIssuer
{
    public function __construct(private readonly AppSettings $settings) {}

    public function issue(string $idAd): array
    {
        return $this->current($idAd);
    }

    public function current(string $idAd): array
    {
        if ($this->settings->rotationDue()) {
            $this->rotateAll();
        }

        $row = AuthCode::query()->where('id_ad', $idAd)->first();

        if (! $row) {
            throw (new ModelNotFoundException)->setModel(AuthCode::class, [$idAd]);
        }

        $expiresAt = $this->settings->expiresAt();
        $refreshSeconds = $this->settings->codeLifetimeSeconds();

        return [
            'id_ad' => $row->id_ad,
            'code' => $row->code,
            'lifetime' => max(1, (int) ceil(now()->diffInSeconds($expiresAt, false))),
            'refresh_seconds' => $refreshSeconds,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function rotateAll(): int
    {
        $count = 0;

        AuthCode::query()->orderBy('id')->each(function (AuthCode $row) use (&$count): void {
            $row->code = $this->generateCode();
            $row->save();
            $count++;
        });

        $this->settings->markRotated();

        return $count;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
