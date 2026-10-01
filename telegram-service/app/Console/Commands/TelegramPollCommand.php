<?php

namespace App\Console\Commands;

use App\Services\BotUpdateHandler;
use App\Services\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Long-poll обновлений Telegram-бота.';

    public function handle(TelegramClient $telegram, BotUpdateHandler $handler): int
    {
        while ($telegram->token() === '') {
            $this->error('Задайте TELEGRAM_BOT_TOKEN в telegram-service/.env и перезапустите контейнер.');
            sleep(15);
        }

        try {
            $telegram->syncBotCommands();
        } catch (Throwable $exception) {
            $this->error('Не удалось обновить список команд бота: '.$exception->getMessage());
            report($exception);
        }

        $this->info('Ожидаю обновления Telegram…');
        $offset = (int) Cache::get('telegram.poll.offset', 0);

        while (true) {
            try {
                $updates = $telegram->getUpdates($offset);

                foreach ($updates as $update) {
                    $handler->handle($update);
                    $offset = ((int) ($update['update_id'] ?? $offset)) + 1;
                    Cache::forever('telegram.poll.offset', $offset);
                }
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());
                report($exception);
                sleep(3);
            }
        }
    }
}
