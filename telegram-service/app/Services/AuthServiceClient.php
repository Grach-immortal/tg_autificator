<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AuthServiceClient
{
    public function verifyCode(string $code, string $idTg): array
    {
        $url = rtrim((string) config('telegram.auth_service_url'), '/').'/api/internal/codes/verify';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Internal-Token' => (string) config('telegram.auth_service_token'),
                ])
                ->post($url, [
                    'code' => $code,
                    'id_tg' => $idTg,
                ]);
        } catch (RequestException $exception) {
            throw new RuntimeException('Auth-service недоступен: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->serverError()) {
            throw new RuntimeException('Auth-service недоступен: '.$response->body());
        }

        if ($response->status() === 404) {
            return [];
        }

        if ($response->status() === 403) {
            return ['fired' => true];
        }

        if (! $response->successful()) {
            throw new RuntimeException('Auth-service: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  list<string>  $ids
     * @return array{linked: list<string>, unlinked: list<string>, fired: list<string>}
     */
    public function checkTelegramIds(array $ids): array
    {
        $url = rtrim((string) config('telegram.auth_service_url'), '/').'/api/internal/tg-ids/check';

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Internal-Token' => (string) config('telegram.auth_service_token'),
            ])
            ->post($url, [
                'id_tg' => array_values($ids),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Auth-service: '.$response->body());
        }

        return [
            'linked' => array_map('strval', $response->json('linked') ?? []),
            'unlinked' => array_map('strval', $response->json('unlinked') ?? []),
            'fired' => array_map('strval', $response->json('fired') ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    public function listTelegramIds(): array
    {
        $url = rtrim((string) config('telegram.auth_service_url'), '/').'/api/internal/tg-ids';

        $response = Http::acceptJson()
            ->withHeaders([
                'X-Internal-Token' => (string) config('telegram.auth_service_token'),
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Auth-service: '.$response->body());
        }

        return array_values(array_unique(array_map('strval', $response->json('id_tg') ?? [])));
    }
}
