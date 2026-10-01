<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Services\KickUnlinkedMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KickUnlinkedMembersTest extends TestCase
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

    public function test_artisan_kicks_unlinked_and_fired_members(): void
    {
        GroupMember::query()->create(['id_tg' => '777', 'in_group' => true, 'is_bot' => false]);
        GroupMember::query()->create(['id_tg' => '888', 'in_group' => true, 'is_bot' => false]);
        GroupMember::query()->create(['id_tg' => '999', 'in_group' => true, 'is_bot' => false]);

        $removed = [];

        Http::fake(function ($request) use (&$removed) {
            $url = $request->url();

            if (str_ends_with($url, '/getChat')) {
                return Http::response(['ok' => true, 'result' => ['title' => 'Рабочая группа']], 200);
            }

            if (str_contains($url, 'getChatMemberCount')) {
                return Http::response(['ok' => true, 'result' => 4], 200);
            }

            if (str_contains($url, 'unbanChatMember')) {
                $removed[(string) $request['user_id']] = 'left';

                return Http::response(['ok' => true, 'result' => true], 200);
            }

            if (str_contains($url, '/banChatMember')) {
                $removed[(string) $request['user_id']] = 'kicked';

                return Http::response(['ok' => true, 'result' => true], 200);
            }

            if (str_contains($url, 'getChatMember')) {
                $userId = (string) $request['user_id'];
                $status = $removed[$userId] ?? 'member';

                return Http::response([
                    'ok' => true,
                    'result' => ['status' => $status, 'user' => ['id' => (int) $userId, 'is_bot' => false]],
                ], 200);
            }

            if (str_contains($url, 'getMe')) {
                return Http::response(['ok' => true, 'result' => ['id' => 99, 'is_bot' => true]], 200);
            }

            if (str_contains($url, 'getChatAdministrators')) {
                return Http::response(['ok' => true, 'result' => []], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids') {
                return Http::response(['id_tg' => []], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids/check') {
                return Http::response([
                    'linked' => ['777'],
                    'unlinked' => ['888'],
                    'fired' => ['999'],
                ], 200);
            }

            if (str_contains($url, 'unbanChatMember') || str_contains($url, 'sendMessage')) {
                return Http::response(['ok' => true, 'result' => true], 200);
            }

            return Http::response(['ok' => true, 'result' => []], 200);
        });

        $this->artisan('telegram:kick-unlinked')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/banChatMember')
            && (string) $request['user_id'] === '888'
            && ! array_key_exists('until_date', $request->data())
            && filter_var($request['revoke_messages'] ?? false, FILTER_VALIDATE_BOOLEAN));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/banChatMember') && (string) $request['user_id'] === '999');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'unbanChatMember')
            && (string) $request['user_id'] === '888'
            && filter_var($request['only_if_banned'] ?? false, FILTER_VALIDATE_BOOLEAN));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && (string) $request['chat_id'] === '888'
            && (string) $request['text'] === 'Для добавления в группу "Рабочая группа" отправьте код из виджета');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && (string) $request['chat_id'] === '999'
            && (string) $request['text'] === KickUnlinkedMembers::NOTICE_FIRED);
        $this->assertFalse((bool) GroupMember::query()->where('id_tg', '888')->value('in_group'));
        $this->assertFalse((bool) GroupMember::query()->where('id_tg', '999')->value('in_group'));
        $this->assertTrue((bool) GroupMember::query()->where('id_tg', '777')->value('in_group'));
    }

    public function test_service_kicks_unlinked_and_fired_members(): void
    {
        GroupMember::query()->create(['id_tg' => '888', 'in_group' => true, 'is_bot' => false]);
        GroupMember::query()->create(['id_tg' => '999', 'in_group' => true, 'is_bot' => false]);

        $removed = [];

        Http::fake(function ($request) use (&$removed) {
            $url = $request->url();

            if (str_ends_with($url, '/getChat')) {
                return Http::response(['ok' => true, 'result' => ['title' => 'Рабочая группа']], 200);
            }

            if (str_contains($url, 'getChatMemberCount')) {
                return Http::response(['ok' => true, 'result' => 3], 200);
            }

            if (str_contains($url, 'unbanChatMember')) {
                $removed[(string) $request['user_id']] = 'left';

                return Http::response(['ok' => true, 'result' => true], 200);
            }

            if (str_contains($url, '/banChatMember')) {
                $removed[(string) $request['user_id']] = 'kicked';

                return Http::response(['ok' => true, 'result' => true], 200);
            }

            if (str_contains($url, 'getChatMember')) {
                $userId = (string) $request['user_id'];
                $status = $removed[$userId] ?? 'member';

                return Http::response([
                    'ok' => true,
                    'result' => ['status' => $status, 'user' => ['id' => (int) $userId, 'is_bot' => false]],
                ], 200);
            }

            if (str_contains($url, 'getMe')) {
                return Http::response(['ok' => true, 'result' => ['id' => 99, 'is_bot' => true]], 200);
            }

            if (str_contains($url, 'getChatAdministrators')) {
                return Http::response(['ok' => true, 'result' => []], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids') {
                return Http::response(['id_tg' => ['999']], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids/check') {
                return Http::response([
                    'linked' => [],
                    'unlinked' => ['888'],
                    'fired' => ['999'],
                ], 200);
            }

            if (str_contains($url, 'unbanChatMember') || str_contains($url, 'sendMessage')) {
                return Http::response(['ok' => true, 'result' => true], 200);
            }

            return Http::response(['ok' => true, 'result' => []], 200);
        });

        $result = app(KickUnlinkedMembers::class)->handle();

        $this->assertSame(2, $result['kicked']);
        $this->assertSame(1, $result['kicked_unlinked']);
        $this->assertSame(1, $result['kicked_fired']);
        $this->assertSame(2, $result['notified']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'unbanChatMember')
            && filter_var($request['only_if_banned'] ?? false, FILTER_VALIDATE_BOOLEAN));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && (string) $request['chat_id'] === '888'
            && (string) $request['text'] === 'Для добавления в группу "Рабочая группа" отправьте код из виджета');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && (string) $request['chat_id'] === '999'
            && (string) $request['text'] === KickUnlinkedMembers::NOTICE_FIRED);
    }

    public function test_already_banned_unlinked_member_is_removed_from_the_list(): void
    {
        GroupMember::query()->create(['id_tg' => '888', 'in_group' => true, 'is_bot' => false]);

        $status = 'kicked';

        Http::fake(function ($request) use (&$status) {
            $url = $request->url();

            if (str_ends_with($url, '/getChat')) {
                return Http::response(['ok' => true, 'result' => ['title' => 'Рабочая группа']], 200);
            }

            if (str_contains($url, 'getChatMemberCount')) {
                return Http::response(['ok' => true, 'result' => 2], 200);
            }

            if (str_contains($url, 'unbanChatMember')) {
                $status = 'left';

                return Http::response(['ok' => true, 'result' => true], 200);
            }

            if (str_contains($url, 'getChatMember')) {
                return Http::response([
                    'ok' => true,
                    'result' => ['status' => $status, 'user' => ['id' => 888, 'is_bot' => false]],
                ], 200);
            }

            if (str_contains($url, 'getMe')) {
                return Http::response(['ok' => true, 'result' => ['id' => 99, 'is_bot' => true]], 200);
            }

            if (str_contains($url, 'getChatAdministrators')) {
                return Http::response(['ok' => true, 'result' => []], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids') {
                return Http::response(['id_tg' => []], 200);
            }

            if ($url === 'http://auth-nginx/api/internal/tg-ids/check') {
                return Http::response([
                    'linked' => [],
                    'unlinked' => ['888'],
                    'fired' => [],
                ], 200);
            }

            if (str_contains($url, 'banChatMember') || str_contains($url, 'unbanChatMember') || str_contains($url, 'sendMessage')) {
                return Http::response(['ok' => true, 'result' => true], 200);
            }

            return Http::response(['ok' => true, 'result' => []], 200);
        });

        $result = app(KickUnlinkedMembers::class)->handle();

        $this->assertSame(1, $result['kicked_unlinked']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'unbanChatMember')
            && (string) $request['user_id'] === '888'
            && filter_var($request['only_if_banned'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse((bool) GroupMember::query()->where('id_tg', '888')->value('in_group'));
    }
}
