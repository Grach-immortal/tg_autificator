<?php

namespace Tests\Feature;

use App\Models\AuthCode;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RotateAuthCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_command_rotates_all_codes(): void
    {
        AuthCode::query()->create(['id_ad' => 'ivanov.i', 'code' => '111111']);
        AuthCode::query()->create(['id_ad' => 'petrov.p', 'code' => '222222']);

        $this->artisan('auth-codes:rotate')
            ->expectsOutputToContain('Обновлено кодов: 2')
            ->assertSuccessful();

        $this->assertNotSame('111111', AuthCode::query()->where('id_ad', 'ivanov.i')->value('code'));
        $this->assertNotSame('222222', AuthCode::query()->where('id_ad', 'petrov.p')->value('code'));
        $this->assertNotNull(app(AppSettings::class)->lastRotatedAt());
    }

    public function test_command_skips_when_interval_has_not_passed(): void
    {
        AuthCode::query()->create(['id_ad' => 'ivanov.i', 'code' => '111111']);

        $this->artisan('auth-codes:rotate')->assertSuccessful();
        $code = AuthCode::query()->where('id_ad', 'ivanov.i')->value('code');

        $this->artisan('auth-codes:rotate')
            ->expectsOutputToContain('ещё не прошёл')
            ->assertSuccessful();

        $this->assertSame($code, AuthCode::query()->where('id_ad', 'ivanov.i')->value('code'));
    }

    public function test_force_rotates_before_interval(): void
    {
        AuthCode::query()->create(['id_ad' => 'ivanov.i', 'code' => '111111']);

        $this->artisan('auth-codes:rotate')->assertSuccessful();
        $code = AuthCode::query()->where('id_ad', 'ivanov.i')->value('code');

        $this->artisan('auth-codes:rotate', ['--force' => true])->assertSuccessful();

        $this->assertNotSame($code, AuthCode::query()->where('id_ad', 'ivanov.i')->value('code'));
    }
}
