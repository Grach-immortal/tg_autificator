<?php

namespace App\Console\Commands;

use App\Services\KickUnlinkedMembers;
use Illuminate\Console\Command;
use Throwable;

class TelegramKickUnlinkedCommand extends Command
{
    protected $signature = 'telegram:kick-unlinked';

    protected $description = 'Кикает из группы участников без связи с AD или со статусом «уволен» и пишет им в личку.';

    public function handle(KickUnlinkedMembers $kicker): int
    {
        try {
            $result = $kicker->handle();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['lines'] as $line) {
            $this->line($line);
        }

        $chat = $result['chat_members'] === null ? '?' : (string) $result['chat_members'];

        $this->info("Участников в группе (Telegram): {$chat}. Известно боту: {$result['known_in_group']}.");
        $this->info("Проверено: {$result['checked']}. Оставлены: {$result['kept']}. Кикнуты без AD: {$result['kicked_unlinked']}. Кикнуты уволенные: {$result['kicked_fired']}.");
        $this->info("Написано в личку: {$result['notified']}. Пропущен владелец: {$result['skipped_creator']}. Пропущены админы: {$result['skipped_admin']}. Не удалось кикнуть: {$result['skipped']}.");

        return self::SUCCESS;
    }
}
