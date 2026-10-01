<?php

namespace App\Services;

use App\Models\GroupMember;
use Throwable;

class BotUpdateHandler
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly AuthServiceClient $auth,
    ) {}

    public function handle(array $update): void
    {
        if (isset($update['chat_member']) && is_array($update['chat_member'])) {
            $this->rememberChatMember($update['chat_member']);

            return;
        }

        if (isset($update['chat_join_request']) && is_array($update['chat_join_request'])) {
            $this->handleJoinRequest($update['chat_join_request']);

            return;
        }

        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chat = $message['chat'] ?? [];
        $from = $message['from'] ?? [];
        $text = trim((string) ($message['text'] ?? ''));
        $chatType = (string) ($chat['type'] ?? '');
        $chatId = $chat['id'] ?? null;
        $userId = $from['id'] ?? null;

        if ($chatId === null) {
            return;
        }

        if ($chatType !== 'private') {
            $this->rememberGroupActivity($message);

            if ($userId !== null && str_starts_with($text, '/id')) {
                $this->telegram->sendMessage($chatId, 'chat_id этой группы: '.$chatId);
            }

            return;
        }

        if ($userId === null || $text === '') {
            return;
        }

        if ($text === '/start') {
            $this->sendGreeting($chatId);

            return;
        }

        if (preg_match('/^\d{6}$/', $text)) {
            $this->handleCode($chatId, (string) $userId, $text);
        }
    }

    private function handleJoinRequest(array $request): void
    {
        $chatId = (string) ($request['chat']['id'] ?? '');

        if ($this->telegram->chatId() !== '' && $chatId !== $this->telegram->chatId()) {
            return;
        }

        $from = $request['from'] ?? [];
        $id = $from['id'] ?? null;

        if ($id === null) {
            return;
        }

        $idTg = (string) $id;
        $this->rememberMember($idTg, (bool) ($from['is_bot'] ?? false), false);

        $dmChatId = $request['user_chat_id'] ?? $id;

        try {
            $check = $this->auth->checkTelegramIds([$idTg]);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if (in_array($idTg, $check['fired'], true)) {
            $this->telegram->declineJoinRequest($idTg);

            try {
                $this->telegram->sendMessage(
                    $dmChatId,
                    'Сотрудник уволен. Если это не так, напишите в поддержку по номеру +7 (495) 994-44-77 (доб. 5555).',
                );
            } catch (Throwable $exception) {
                report($exception);
            }

            return;
        }

        if (in_array($idTg, $check['linked'], true) && $this->telegram->approveJoinRequest($idTg) === 'approved') {
            $this->rememberMember($idTg, false, true);

            try {
                $this->telegram->sendMessage($dmChatId, $this->withGroupLink('Вы добавлены в группу.'));
            } catch (Throwable $exception) {
                report($exception);
            }

            return;
        }

        try {
            $this->sendGreeting($dmChatId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function sendGreeting(int|string $chatId): void
    {
        $link = $this->telegram->inviteLink();
        $group = $link !== '' ? 'группу '.$link : 'группу';

        $this->telegram->sendMessage(
            $chatId,
            "Здравствуйте! Это бот-верификатор, он обрабатывает заявки на вступление в {$group}. Введите в чат код-верификации, результатом будет сообщение:\n"
            ."А) вы добавлены в группу и можете сразу перейти в чат.\n"
            ."Б) о отклонении и причина отклонения\n\n"
            .'Если бот не сработал необходимо ввести /start в чате для запуска',
        );
    }

    private function handleCode(int|string $chatId, string $userId, string $text): void
    {
        if (! preg_match('/^\d{6}$/', $text)) {
            $this->telegram->sendMessage(
                $chatId,
                'Нужен ровно 6-значный код. Попробуйте ещё раз или нажмите /cancel.',
                $this->hideKeyboard(),
            );

            return;
        }

        try {
            $result = $this->auth->verifyCode($text, $userId);
        } catch (Throwable $exception) {
            $this->telegram->sendMessage(
                $chatId,
                'Не удалось проверить код. Попробуйте позже.',
                $this->hideKeyboard(),
            );
            report($exception);

            return;
        }

        if ($result === []) {
            $this->telegram->sendMessage(
                $chatId,
                'Код не найден. Возьмите актуальный код из виджета и введите снова.',
                $this->hideKeyboard(),
            );

            return;
        }

        if (($result['fired'] ?? false) === true) {
            $this->telegram->declineJoinRequest($userId);
            $this->telegram->sendMessage(
                $chatId,
                'Сотрудник уволен. Если это не так, напишите в поддержку по номеру +7 (495) 994-44-77 (доб. 5555).',
                $this->hideKeyboard(),
            );

            return;
        }

        $this->rememberMember($userId, false, false);
        $this->inviteToGroup($chatId, $userId, (string) ($result['id_ad'] ?? ''));
    }

    private function rememberChatMember(array $event): void
    {
        $chatId = (string) (($event['chat']['id'] ?? ''));

        if ($this->telegram->chatId() !== '' && $chatId !== $this->telegram->chatId()) {
            return;
        }

        $member = $event['new_chat_member'] ?? [];
        $user = $member['user'] ?? [];
        $id = $user['id'] ?? null;

        if ($id === null) {
            return;
        }

        $status = (string) ($member['status'] ?? '');
        $this->rememberMember((string) $id, (bool) ($user['is_bot'] ?? false), ! in_array($status, ['left', 'kicked'], true));
    }

    private function rememberGroupActivity(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');

        if ($this->telegram->chatId() !== '' && $chatId !== $this->telegram->chatId()) {
            return;
        }

        $from = $message['from'] ?? [];

        if (isset($from['id'])) {
            $this->rememberMember((string) $from['id'], (bool) ($from['is_bot'] ?? false), true);
        }

        foreach ($message['new_chat_members'] ?? [] as $user) {
            if (! is_array($user) || ! isset($user['id'])) {
                continue;
            }

            $this->rememberMember((string) $user['id'], (bool) ($user['is_bot'] ?? false), true);
        }

        $left = $message['left_chat_member'] ?? null;

        if (is_array($left) && isset($left['id'])) {
            $this->rememberMember((string) $left['id'], (bool) ($left['is_bot'] ?? false), false);
        }
    }

    private function rememberMember(string $idTg, bool $isBot, bool $inGroup = true): void
    {
        GroupMember::query()->updateOrCreate(
            ['id_tg' => $idTg],
            [
                'is_bot' => $isBot,
                'in_group' => $inGroup,
            ],
        );
    }

    private function inviteToGroup(int|string $chatId, string $userId, string $idAd): void
    {
        if ($this->telegram->chatId() === '') {
            $this->telegram->sendMessage(
                $chatId,
                'Код принят, сотрудник '.$idAd.' связан. TELEGRAM_CHAT_ID не задан — в группу добавить нельзя.',
                $this->hideKeyboard(),
            );

            return;
        }

        $approval = $this->telegram->approveJoinRequest($userId);
        $text = 'Код принят. Сотрудник '.$idAd.' связан с этим Telegram.';

        if ($approval === 'approved') {
            $this->rememberMember($userId, false, true);
            $text .= ' Вы добавлены в группу.';
            $this->telegram->sendMessage($chatId, $this->withGroupLink($text), $this->hideKeyboard());

            return;
        }

        if ($approval === 'already_member') {
            $this->rememberMember($userId, false, true);
            $text .= ' Вы уже в группе.';
            $this->telegram->sendMessage($chatId, $this->withGroupLink($text), $this->hideKeyboard());

            return;
        }

        $this->telegram->unbanIfNeeded($userId);
        $text .= ' Заявка на вступление не найдена. Подайте её в группе — она будет принята автоматически.';
        $this->telegram->sendMessage($chatId, $this->withGroupLink($text), $this->hideKeyboard());
    }

    private function withGroupLink(string $text): string
    {
        $link = $this->telegram->inviteLink();

        if ($link === '') {
            return $text;
        }

        return $text."\n".$link;
    }

    /**
     * @return array<string, string>
     */
    private function hideKeyboard(): array
    {
        return [
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
            ], JSON_UNESCAPED_UNICODE),
        ];
    }
}
