<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramClient
{
    public function getUpdates(int $offset = 0): array
    {
        try {
            $response = $this->http()
                ->timeout(35)
                ->get($this->methodUrl('getUpdates'), [
                    'offset' => $offset,
                    'timeout' => 25,
                    'allowed_updates' => json_encode(['message', 'chat_join_request', 'chat_member', 'my_chat_member']),
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Telegram getUpdates: '.$this->redact($exception->getMessage()));
        }

        if (! $response->ok() || ! $response->json('ok')) {
            throw new RuntimeException('Telegram getUpdates: '.$this->redact(trim((string) $response->body()) ?: 'empty response'));
        }

        return $response->json('result') ?? [];
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra));
    }

    public function syncBotCommands(): void
    {
        $this->call('setMyCommands', [
            'commands' => json_encode([]),
        ]);
    }

    /**
     * @return 'approved'|'already_member'|'failed'
     */
    public function approveJoinRequest(int|string $userId): string
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return 'failed';
        }

        try {
            $this->call('approveChatJoinRequest', [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'USER_ALREADY_PARTICIPANT')) {
                return 'already_member';
            }

            return 'failed';
        }

        return 'approved';
    }

    public function declineJoinRequest(int|string $userId): void
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return;
        }

        try {
            $this->call('declineChatJoinRequest', [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
        } catch (RuntimeException) {
        }
    }

    public function unbanIfNeeded(int|string $userId): void
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return;
        }

        $this->http()->post($this->methodUrl('unbanChatMember'), [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => true,
        ]);
    }

    public function createInviteLink(): ?string
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return null;
        }

        $result = $this->call('createChatInviteLink', [
            'chat_id' => $chatId,
            'expire_date' => now()->addHour()->timestamp,
            'member_limit' => 1,
            'creates_join_request' => 'false',
        ]);

        if (($result['creates_join_request'] ?? false) === true) {
            throw new RuntimeException('Telegram createChatInviteLink: ссылка всё ещё требует заявку.');
        }

        $link = $result['invite_link'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }

    /**
     * @return array{status: string, can_invite_users: bool}|null
     */
    public function botChatRights(): ?array
    {
        $me = $this->getMe();
        $member = $this->getChatMember($me['id'] ?? 0);

        if ($member === null) {
            return null;
        }

        return [
            'status' => (string) ($member['status'] ?? ''),
            'can_invite_users' => (bool) ($member['can_invite_users'] ?? ($member['status'] === 'creator')),
        ];
    }

    public function getMe(): array
    {
        return $this->call('getMe', []);
    }

    public function getChatMember(int|string $userId): ?array
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return null;
        }

        $response = $this->http()->post($this->methodUrl('getChatMember'), [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        if (! $response->ok() || ! $response->json('ok')) {
            return null;
        }

        return $response->json('result');
    }

    public function getChatAdministrators(): array
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return [];
        }

        $response = $this->http()->post($this->methodUrl('getChatAdministrators'), [
            'chat_id' => $chatId,
        ]);

        if (! $response->ok() || ! $response->json('ok')) {
            return [];
        }

        return $response->json('result') ?? [];
    }

    public function chatMemberCount(): ?int
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return null;
        }

        $response = $this->http()->post($this->methodUrl('getChatMemberCount'), [
            'chat_id' => $chatId,
        ]);

        if (! $response->ok() || ! $response->json('ok')) {
            return null;
        }

        $count = $response->json('result');

        return is_numeric($count) ? (int) $count : null;
    }

    public function inviteLink(): string
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return '';
        }

        $cacheKey = 'telegram.invite_link.'.$chatId;
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $chat = $this->call('getChat', ['chat_id' => $chatId]);
        } catch (Throwable) {
            return '';
        }

        $link = trim((string) ($chat['invite_link'] ?? ''));

        if ($link !== '') {
            Cache::put($cacheKey, $link, 3600);
        }

        return $link;
    }

    public function chatTitle(): string
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return '';
        }

        try {
            $chat = $this->call('getChat', ['chat_id' => $chatId]);
        } catch (Throwable) {
            return '';
        }

        return trim((string) ($chat['title'] ?? ''));
    }

    public function notifyUser(int|string $userId, string $text, array $extra = []): bool
    {
        try {
            $this->sendMessage($userId, $text, $extra);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function isGroupAdmin(int|string $userId): bool
    {
        $member = $this->getChatMember($userId);
        $status = (string) ($member['status'] ?? '');

        return in_array($status, ['creator', 'administrator'], true);
    }

    public function kickUser(int|string $userId): bool
    {
        $chatId = $this->chatId();

        if ($chatId === '') {
            return false;
        }

        if (! $this->banMember($chatId, $userId)) {
            return false;
        }

        $status = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            if ($attempt > 0) {
                usleep(500000);
            }

            $status = $this->memberStatus($userId);

            if (in_array($status, ['left', 'kicked'], true)) {
                break;
            }

            $status = null;
        }

        if ($status === null) {
            return false;
        }

        if ($status !== 'kicked') {
            return true;
        }

        $unbanned = $this->http()->post($this->methodUrl('unbanChatMember'), [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => true,
        ]);

        if (! $unbanned->ok() || ! $unbanned->json('ok')) {
            return false;
        }

        if (in_array($this->memberStatus($userId), ['creator', 'administrator', 'member', 'restricted'], true)) {
            $this->banMember($chatId, $userId);

            return false;
        }

        return true;
    }

    private function banMember(string $chatId, int|string $userId): bool
    {
        $banned = $this->http()->post($this->methodUrl('banChatMember'), [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'revoke_messages' => true,
        ]);

        if ($banned->ok() && $banned->json('ok')) {
            return true;
        }

        return str_contains((string) $banned->body(), 'USER_NOT_PARTICIPANT');
    }

    private function memberStatus(int|string $userId): string
    {
        return (string) ($this->getChatMember($userId)['status'] ?? '');
    }

    public function chatId(): string
    {
        return (string) config('telegram.chat_id');
    }

    public function token(): string
    {
        return (string) config('telegram.bot_token');
    }

    private function call(string $method, array $payload): array
    {
        try {
            $response = $this->http()->post($this->methodUrl($method), $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException("Telegram {$method}: ".$this->redact($exception->getMessage()));
        }

        if (! $response->ok() || ! $response->json('ok')) {
            $reason = trim((string) $response->body());

            if ($reason === '') {
                $reason = $response->toException()?->getMessage() ?: $response->reason() ?: 'empty response';
            }

            throw new RuntimeException("Telegram {$method}: ".$this->redact($reason));
        }

        $result = $response->json('result');

        if (is_string($result) && $result !== '') {
            return ['invite_link' => $result];
        }

        return is_array($result) ? $result : [];
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asForm()
            ->timeout(20)
            ->connectTimeout(10)
            ->withOptions([
                'force_ip_resolve' => 'v4',
                'version' => 1.1,
            ])
            ->retry(4, 500, fn ($exception) => $exception instanceof ConnectionException, false);
    }

    private function methodUrl(string $method): string
    {
        $token = $this->token();

        if ($token === '') {
            throw new RuntimeException('Не задан TELEGRAM_BOT_TOKEN.');
        }

        return rtrim((string) config('telegram.api_url'), '/').'/bot'.$token.'/'.$method;
    }

    private function redact(string $message): string
    {
        $token = $this->token();

        return $token === '' ? $message : str_replace($token, '***', $message);
    }
}
