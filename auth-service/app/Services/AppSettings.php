<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AppSettings
{
    public const LAST_ROTATED_CACHE_KEY = 'auth_codes.last_rotated_at';

    public function codeLifetimeSeconds(): int
    {
        $seconds = $this->value('refresh_seconds');

        if ($seconds === null) {
            $seconds = $this->value('code_lifetime_seconds', 180);
        }

        return max(1, (int) $seconds);
    }

    public function lastRotatedAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_ROTATED_CACHE_KEY);

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    public function markRotated(?Carbon $at = null): void
    {
        Cache::forever(self::LAST_ROTATED_CACHE_KEY, ($at ?? now())->toIso8601String());
    }

    public function rotationDue(): bool
    {
        $lastRotatedAt = $this->lastRotatedAt();

        if ($lastRotatedAt === null) {
            return true;
        }

        return $lastRotatedAt->copy()->addSeconds($this->codeLifetimeSeconds())->lte(now());
    }

    public function expiresAt(): Carbon
    {
        $lastRotatedAt = $this->lastRotatedAt() ?? now();

        return $lastRotatedAt->copy()->addSeconds($this->codeLifetimeSeconds());
    }

    private function value(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    private function all(): array
    {
        $path = base_path('settings.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Файл settings.json должен содержать JSON-объект.');
        }

        return $decoded;
    }
}
