<?php

namespace App\Services;

use App\Models\GroupMember;
use RuntimeException;
use Throwable;

class KickUnlinkedMembers
{
    public const NOTICE_FIRED = 'Сотрудник по коду уволен. Если это не так, напишите в поддержку по номеру +7 (495) 994-44-77 (доб. 5555).';

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly AuthServiceClient $auth,
    ) {}

    /**
     * @return array{
     *     checked: int,
     *     kicked: int,
     *     kept: int,
     *     skipped: int,
     *     kicked_unlinked: int,
     *     kicked_fired: int,
     *     notified: int,
     *     skipped_creator: int,
     *     skipped_admin: int,
     *     chat_members: ?int,
     *     known_in_group: int,
     *     lines: list<string>
     * }
     */
    public function handle(): array
    {
        if ($this->telegram->chatId() === '') {
            throw new RuntimeException('Не задан TELEGRAM_CHAT_ID.');
        }

        $this->syncKnownMembers();

        $botId = (string) ($this->telegram->getMe()['id'] ?? '');
        $candidates = [];
        $skippedCreator = 0;
        $skippedAdmin = 0;
        $knownInGroup = 0;
        $lines = [];

        foreach ($this->knownTelegramIds() as $idTg) {
            if ($idTg === $botId) {
                continue;
            }

            $info = $this->telegram->getChatMember($idTg);
            $status = (string) ($info['status'] ?? '');
            $stored = GroupMember::query()->where('id_tg', $idTg)->first();
            $isBot = (bool) ($info['user']['is_bot'] ?? $stored?->is_bot);

            if ($status === '' && (bool) $stored?->in_group) {
                $status = 'member';
            }

            $this->rememberMember($idTg, $isBot, $this->isPresent($status));

            if ($isBot || (! $this->isPresent($status) && $status !== 'kicked')) {
                continue;
            }

            if ($this->isPresent($status)) {
                $knownInGroup++;
            }

            if ($status === 'creator') {
                $skippedCreator++;
                $lines[] = $idTg.': владелеца группы, бот не может кикнуть.';

                continue;
            }

            if ($status === 'administrator') {
                $skippedAdmin++;
                $lines[] = $idTg.': админа группы, бот не может кикнуть.';

                continue;
            }

            $candidates[] = $idTg;
        }

        $chatMembers = $this->telegram->chatMemberCount();

        if ($candidates === []) {
            if (is_int($chatMembers) && $chatMembers > $knownInGroup + 1) {
                $lines[] = 'В группе '.($chatMembers - 1).' человек, бот знает '.$knownInGroup.'. Остальных не видел: пусть напишут в группе или перезайдут.';
            }

            return [
                'checked' => 0,
                'kicked' => 0,
                'kept' => 0,
                'skipped' => 0,
                'kicked_unlinked' => 0,
                'kicked_fired' => 0,
                'notified' => 0,
                'skipped_creator' => $skippedCreator,
                'skipped_admin' => $skippedAdmin,
                'chat_members' => $chatMembers,
                'known_in_group' => $knownInGroup,
                'lines' => $lines,
            ];
        }

        $check = $this->auth->checkTelegramIds($candidates);
        $toKick = array_values(array_unique([
            ...$check['unlinked'],
            ...$check['fired'],
        ]));

        $kickedUnlinked = 0;
        $kickedFired = 0;
        $notified = 0;
        $failed = 0;
        $unlinkedNotice = $this->unlinkedNotice();

        foreach ($toKick as $idTg) {
            $fired = in_array($idTg, $check['fired'], true);

            if (! $this->telegram->kickUser($idTg)) {
                $failed++;
                $lines[] = $idTg.': не удалось кикнуть'.($fired ? ' (уволен)' : ' (нет записи в tg_ids)').'.';

                continue;
            }

            $this->rememberMember($idTg, false, false);

            if ($this->telegram->notifyUser($idTg, $fired ? self::NOTICE_FIRED : $unlinkedNotice)) {
                $notified++;
            }

            if ($fired) {
                $kickedFired++;
                $lines[] = $idTg.': кикнут, сотрудник уволен.';
            } else {
                $kickedUnlinked++;
                $lines[] = $idTg.': кикнут, нет записи в tg_ids.';
            }
        }

        if (is_int($chatMembers) && $chatMembers > $knownInGroup + 1) {
            $lines[] = 'В группе было больше участников, чем бот знает. Новые увидит, если напишут в группе или перезайдут.';
        }

        return [
            'checked' => count($candidates),
            'kicked' => $kickedUnlinked + $kickedFired,
            'kept' => count($check['linked']),
            'skipped' => $failed,
            'kicked_unlinked' => $kickedUnlinked,
            'kicked_fired' => $kickedFired,
            'notified' => $notified,
            'skipped_creator' => $skippedCreator,
            'skipped_admin' => $skippedAdmin,
            'chat_members' => $chatMembers,
            'known_in_group' => $knownInGroup,
            'lines' => $lines,
        ];
    }

    /**
     * @return list<string>
     */
    private function knownTelegramIds(): array
    {
        $ids = GroupMember::query()->pluck('id_tg')->all();

        try {
            foreach ($this->auth->listTelegramIds() as $id) {
                $ids[] = $id;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        foreach ($this->telegram->getChatAdministrators() as $admin) {
            $id = $admin['user']['id'] ?? null;

            if ($id !== null) {
                $ids[] = (string) $id;
            }
        }

        $ids = array_values(array_unique(array_map('strval', $ids)));
        sort($ids);

        return $ids;
    }

    private function syncKnownMembers(): void
    {
        foreach ($this->telegram->getChatAdministrators() as $admin) {
            $user = $admin['user'] ?? [];
            $id = $user['id'] ?? null;

            if ($id === null) {
                continue;
            }

            $this->rememberMember((string) $id, (bool) ($user['is_bot'] ?? false), true);
        }
    }

    private function rememberMember(string $idTg, bool $isBot, bool $inGroup): void
    {
        GroupMember::query()->updateOrCreate(
            ['id_tg' => $idTg],
            [
                'is_bot' => $isBot,
                'in_group' => $inGroup,
            ],
        );
    }

    private function isPresent(string $status): bool
    {
        return in_array($status, ['creator', 'administrator', 'member', 'restricted'], true);
    }

    private function unlinkedNotice(): string
    {
        $title = $this->telegram->chatTitle();

        $text = $title === ''
            ? 'Для добавления в группу отправьте код из виджета'
            : 'Для добавления в группу "'.$title.'" отправьте код из виджета';

        $link = $this->telegram->inviteLink();

        if ($link === '') {
            return $text;
        }

        return $text."\n".$link;
    }
}
