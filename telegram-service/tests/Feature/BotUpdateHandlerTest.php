<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Services\BotUpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotUpdateHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'telegram.bot_token' => 'test-token',
            'telegram.chat_id' => '-100123',
            'telegram.api_url' => 'https://api.telegram.org',
            'telegram.auth_service_url' => 'http://auth-nginx',
            'telegram.auth_service_token' => 'secret',
        ]);
    }

    public function test_join_request_asks_to_verify(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/tg-ids/check' => Http::response([
                'linked' => [],
                'unlinked' => ['555'],
                'fired' => [],
            ], 200),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle([
            'update_id' => 1,
            'chat_join_request' => [
                'chat' => ['id' => -100123, 'type' => 'supergroup'],
                'from' => ['id' => 555, 'is_bot' => false],
                'user_chat_id' => 555,
                'date' => time(),
            ],
        ]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'declineChatJoinRequest'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'approveChatJoinRequest'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'бот-верификатор')
            && str_contains((string) $request['text'], 'код-верификации'));
        $this->assertTrue(GroupMember::query()->where('id_tg', '555')->exists());
    }

    public function test_verify_button_asks_for_code(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle($this->privateMessage('верифицироваться'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_valid_code_invites_user_to_group(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/codes/verify' => Http::response([
                'id_ad' => 'ivanov.i',
                'id_tg' => '555',
            ], 200),
            'api.telegram.org/*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'member', 'user' => ['id' => 555, 'is_bot' => false]],
            ], 200),
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/approveChatJoinRequest' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/unbanChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/createChatInviteLink' => Http::response([
                'ok' => true,
                'result' => ['invite_link' => 'https://t.me/+invite'],
            ], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $handler = app(BotUpdateHandler::class);
        $handler->handle($this->privateMessage('123456'));

        Http::assertSent(function ($request) {
            return $request->url() === 'http://auth-nginx/api/internal/codes/verify'
                && $request['code'] === '123456'
                && $request['id_tg'] === '555';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Вы добавлены в группу')
                && str_contains((string) $request['text'], 'ivanov.i')
                && str_contains((string) $request['text'], 'https://t.me/+group');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'createChatInviteLink'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'exportChatInviteLink'));
    }

    public function test_unknown_code_is_rejected(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/codes/verify' => Http::response(['message' => 'Код не найден.'], 404),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $handler = app(BotUpdateHandler::class);
        $handler->handle($this->privateMessage('000000'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Код не найден');
        });
    }

    public function test_without_join_request_asks_to_submit_one_instead_of_sending_link(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/codes/verify' => Http::response([
                'id_ad' => 'ivanov.i',
                'id_tg' => '555',
            ], 200),
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/approveChatJoinRequest' => Http::response(['ok' => false, 'description' => 'Bad Request: USER_ID_INVALID'], 400),
            'api.telegram.org/*/declineChatJoinRequest' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'kicked', 'user' => ['id' => 555, 'is_bot' => false]],
            ], 200),
            'api.telegram.org/*/banChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/unbanChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/createChatInviteLink' => Http::response([
                'ok' => true,
                'result' => [
                    'invite_link' => 'https://t.me/+request',
                    'creates_join_request' => false,
                    'member_limit' => 1,
                ],
            ], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $handler = app(BotUpdateHandler::class);
        $handler->handle($this->privateMessage('123456'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'unbanChatMember')
            && filter_var($request['only_if_banned'] ?? false, FILTER_VALIDATE_BOOLEAN));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Подайте её в группе')
                && str_contains((string) $request['text'], 'https://t.me/+group');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'createChatInviteLink'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'banChatMember') && ! str_contains($request->url(), 'unbanChatMember'));
    }

    public function test_linked_user_join_request_is_approved(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/tg-ids/check' => Http::response([
                'linked' => ['555'],
                'unlinked' => [],
                'fired' => [],
            ], 200),
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/approveChatJoinRequest' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle([
            'update_id' => 3,
            'chat_join_request' => [
                'chat' => ['id' => -100123, 'type' => 'supergroup'],
                'from' => ['id' => 555, 'is_bot' => false],
                'user_chat_id' => 555,
                'date' => time(),
            ],
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'approveChatJoinRequest')
            && (string) $request['user_id'] === '555');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Вы добавлены в группу');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'createChatInviteLink'));
    }

    public function test_already_member_is_asked_to_submit_join_request_without_link(): void
    {
        Http::fake([
            'http://auth-nginx/api/internal/codes/verify' => Http::response([
                'id_ad' => 'ivanov.i',
                'id_tg' => '555',
            ], 200),
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/approveChatJoinRequest' => Http::response(['ok' => false, 'description' => 'Bad Request: USER_ALREADY_PARTICIPANT'], 400),
            'api.telegram.org/*/declineChatJoinRequest' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'kicked', 'user' => ['id' => 555, 'is_bot' => false]],
            ], 200),
            'api.telegram.org/*/banChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/unbanChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/createChatInviteLink' => Http::response([
                'ok' => true,
                'result' => [
                    'invite_link' => 'https://t.me/+request',
                    'creates_join_request' => false,
                ],
            ], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        $handler = app(BotUpdateHandler::class);
        $handler->handle($this->privateMessage('123456'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'unbanChatMember'));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Вы уже в группе')
                && str_contains((string) $request['text'], 'https://t.me/+group')
                && ! str_contains((string) $request['text'], 'Подать заявку');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'banChatMember') && ! str_contains($request->url(), 'unbanChatMember'));
    }

    public function test_old_kick_button_does_not_kick_members(): void
    {
        GroupMember::query()->create(['id_tg' => '888', 'in_group' => true, 'is_bot' => false]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle($this->privateMessage('Проверить группу'));
        app(BotUpdateHandler::class)->handle($this->privateMessage('/kick_unlinked'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'banChatMember'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'верифицироваться'));
        $this->assertTrue((bool) GroupMember::query()->where('id_tg', '888')->value('in_group'));
    }

    public function test_group_join_is_remembered(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle([
            'update_id' => 2,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => -100123, 'type' => 'supergroup'],
                'from' => ['id' => 555, 'is_bot' => false],
                'new_chat_members' => [
                    ['id' => 888, 'is_bot' => false],
                ],
            ],
        ]);

        $this->assertTrue((bool) GroupMember::query()->where('id_tg', '888')->where('in_group', true)->value('in_group'));
        $this->assertTrue((bool) GroupMember::query()->where('id_tg', '555')->where('in_group', true)->value('in_group'));
    }

    public function test_absent_user_can_submit_join_request_again(): void
    {
        Http::fake([
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'kicked', 'user' => ['id' => 555, 'is_bot' => false]],
            ], 200),
            'api.telegram.org/*/unbanChatMember' => Http::response(['ok' => true, 'result' => true], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle($this->privateMessage('/start'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'бот-верификатор')
            && str_contains((string) $request['text'], 'https://t.me/+group')
            && str_contains((string) $request['text'], '/start'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'unbanChatMember'));
    }

    public function test_current_member_is_not_unbanned(): void
    {
        Http::fake([
            'api.telegram.org/*/getChat' => Http::response([
                'ok' => true,
                'result' => ['title' => 'Рабочая группа', 'invite_link' => 'https://t.me/+group'],
            ], 200),
            'api.telegram.org/*/getChatMember' => Http::response([
                'ok' => true,
                'result' => ['status' => 'member', 'user' => ['id' => 555, 'is_bot' => false]],
            ], 200),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
        ]);

        app(BotUpdateHandler::class)->handle($this->privateMessage('/start'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'бот-верификатор')
            && str_contains((string) $request['text'], 'https://t.me/+group')
            && str_contains((string) $request['text'], '/start'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'unbanChatMember'));
    }

    private function privateMessage(string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'text' => $text,
                'chat' => ['id' => 555, 'type' => 'private'],
                'from' => ['id' => 555],
            ],
        ];
    }
}
