<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\AuthCode;
use App\Models\TgId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['widget.internal_token' => 'change-me-shared-internal-token']);
    }

    public function test_unknown_code_is_not_found(): void
    {
        $this->withInternalToken()
            ->postJson('/api/internal/codes/verify', [
                'code' => '000000',
                'id_tg' => '1001',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Код не найден.');
    }

    public function test_valid_code_links_telegram_to_ad(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'ivanov.i',
            'code' => '123456',
        ]);

        $this->withInternalToken()
            ->postJson('/api/internal/codes/verify', [
                'code' => '123456',
                'id_tg' => '1001',
            ])
            ->assertOk()
            ->assertJsonPath('id_ad', 'ivanov.i')
            ->assertJsonPath('id_tg', '1001');

        $this->assertDatabaseHas('tg_ids', [
            'id_ad' => 'ivanov.i',
            'id_tg' => '1001',
        ]);
        $this->assertNotSame('123456', AuthCode::query()->where('id_ad', 'ivanov.i')->value('code'));
    }

    public function test_one_ad_can_have_many_telegram_ids(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'ivanov.i',
            'code' => '111111',
        ]);

        $this->withInternalToken()->postJson('/api/internal/codes/verify', [
            'code' => '111111',
            'id_tg' => '1001',
        ])->assertOk();

        AuthCode::query()->where('id_ad', 'ivanov.i')->update(['code' => '222222']);

        $this->withInternalToken()->postJson('/api/internal/codes/verify', [
            'code' => '222222',
            'id_tg' => '1002',
        ])->assertOk();

        $this->assertSame(2, TgId::query()->where('id_ad', 'ivanov.i')->count());
    }

    public function test_same_telegram_id_stays_unique_and_can_rebind(): void
    {
        AuthCode::query()->create(['id_ad' => 'ivanov.i', 'code' => '111111']);
        AuthCode::query()->create(['id_ad' => 'petrov.p', 'code' => '222222']);

        $this->withInternalToken()->postJson('/api/internal/codes/verify', [
            'code' => '111111',
            'id_tg' => '1001',
        ])->assertOk();

        $this->withInternalToken()->postJson('/api/internal/codes/verify', [
            'code' => '222222',
            'id_tg' => '1001',
        ])->assertOk()->assertJsonPath('id_ad', 'petrov.p');

        $this->assertSame(1, TgId::query()->where('id_tg', '1001')->count());
        $this->assertSame('petrov.p', TgId::query()->where('id_tg', '1001')->value('id_ad'));
    }

    public function test_check_splits_linked_and_unlinked_telegram_ids(): void
    {
        AuthCode::query()->create(['id_ad' => 'ivanov.i', 'code' => '111111']);
        TgId::query()->create(['id_ad' => 'ivanov.i', 'id_tg' => '1001']);

        AuthCode::query()->create([
            'id_ad' => 'kozlov.d',
            'code' => '333333',
            'status_employee' => EmployeeStatus::Fired,
        ]);
        TgId::query()->create(['id_ad' => 'kozlov.d', 'id_tg' => '1003']);

        $this->withInternalToken()
            ->postJson('/api/internal/tg-ids/check', [
                'id_tg' => ['1001', '1002', '1003'],
            ])
            ->assertOk()
            ->assertJsonPath('linked', ['1001'])
            ->assertJsonPath('unlinked', ['1002'])
            ->assertJsonPath('fired', ['1003']);

        $this->withInternalToken()
            ->getJson('/api/internal/tg-ids')
            ->assertOk()
            ->assertJsonPath('id_tg', ['1001', '1003']);
    }

    public function test_fired_employee_code_is_rejected(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'kozlov.d',
            'code' => '456789',
            'status_employee' => EmployeeStatus::Fired,
        ]);

        $this->withInternalToken()
            ->postJson('/api/internal/codes/verify', [
                'code' => '456789',
                'id_tg' => '1001',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Сотрудник уволен.');
    }

    public function test_rejects_missing_internal_token(): void
    {
        $this->postJson('/api/internal/codes/verify', [
            'code' => '123456',
            'id_tg' => '1001',
        ])->assertForbidden();
    }

    private function withInternalToken()
    {
        return $this->withHeader('X-Internal-Token', 'change-me-shared-internal-token');
    }
}
