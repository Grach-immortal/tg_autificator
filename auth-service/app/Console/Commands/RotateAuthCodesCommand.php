<?php

namespace App\Console\Commands;

use App\Services\AppSettings;
use App\Services\AuthCodeIssuer;
use Illuminate\Console\Command;

class RotateAuthCodesCommand extends Command
{
    protected $signature = 'auth-codes:rotate {--force : Обновить коды, даже если интервал из настроек ещё не прошёл}';

    protected $description = 'Обновляет все коды. Интервал берётся из settings.json → refresh_seconds.';

    public function handle(AppSettings $settings, AuthCodeIssuer $issuer): int
    {
        if (! $this->option('force') && ! $settings->rotationDue()) {
            $this->info('Интервал из settings.json ещё не прошёл, коды не менялись.');

            return self::SUCCESS;
        }

        $count = $issuer->rotateAll();

        $this->info("Обновлено кодов: {$count}. Следующее обновление через {$settings->codeLifetimeSeconds()} сек.");

        return self::SUCCESS;
    }
}
