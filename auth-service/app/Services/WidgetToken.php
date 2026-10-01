<?php

namespace App\Services;

class WidgetToken
{
    public function issue(string $idAd, ?int $ttl = null): string
    {
        $payload = json_encode([
            'id_ad' => $idAd,
            'exp' => time() + ($ttl ?? (int) config('widget.token_ttl')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $encoded = $this->base64UrlEncode($payload);

        return $encoded.'.'.$this->sign($encoded);
    }

    public function verify(string $token): ?string
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$encoded, $signature] = $parts;

        if (! hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encoded), true);

        if (! is_array($payload) || ! is_string($payload['id_ad'] ?? null) || $payload['id_ad'] === '') {
            return null;
        }

        if (! is_numeric($payload['exp'] ?? null) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload['id_ad'];
    }

    private function sign(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, $this->secret());
    }

    private function secret(): string
    {
        $secret = (string) config('widget.token_secret');

        return $secret !== '' ? $secret : (string) config('app.key');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
