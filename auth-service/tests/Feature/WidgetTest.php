<?php

namespace Tests\Feature;

use App\Models\AuthCode;
use App\Services\WidgetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_widget_page_renders_without_employees(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_raw_id_ad_is_signed_and_redirected(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'ivanov.i',
            'code' => '111111',
        ]);

        $response = $this->get('/?id_ad=ivanov.i');

        $response->assertRedirect();
        $token = (string) $response->headers->get('Location');
        $this->assertStringContainsString('id_ad=', $token);

        $query = parse_url($token, PHP_URL_QUERY);
        parse_str((string) $query, $params);

        $this->assertSame('ivanov.i', app(WidgetToken::class)->verify($params['id_ad']));

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('/widget.js', false);
    }

    public function test_api_rejects_unsigned_id_ad(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'ivanov.i',
            'code' => '111111',
        ]);

        $this->getJson('/api/widget/code?id_ad=ivanov.i')
            ->assertForbidden()
            ->assertJsonPath('message', 'Недействительный или просроченный id_ad.');
    }

    public function test_api_returns_current_code_for_signed_id_ad(): void
    {
        AuthCode::query()->create([
            'id_ad' => 'ivanov.i',
            'code' => '111111',
        ]);

        $token = app(WidgetToken::class)->issue('ivanov.i');

        $first = $this->getJson('/api/widget/code?id_ad='.$token);
        $first->assertOk()->assertJsonPath('id_ad', 'ivanov.i');

        $code = $first->json('code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame($code, AuthCode::query()->where('id_ad', 'ivanov.i')->value('code'));
        $this->assertGreaterThan(0, $first->json('lifetime'));
        $this->assertSame(180, $first->json('refresh_seconds'));

        $this->getJson('/api/widget/code?id_ad='.$token)
            ->assertOk()
            ->assertJsonPath('code', $code);
    }
}
