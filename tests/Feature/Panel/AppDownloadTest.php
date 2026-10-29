<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_gets_download_config(): void
    {
        config()->set('payguard.apk', [
            'url' => 'https://payguard.micaleth.com/apk/payguard.apk',
            'version' => '1.0.0',
            'date' => '28 oct. 2026',
        ]);
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->getJson('/api/panel/app-download');

        $response->assertOk();
        $response->assertExactJson(['data' => [
            'url' => 'https://payguard.micaleth.com/apk/payguard.apk',
            'version' => '1.0.0',
            'date' => '28 oct. 2026',
        ]]);
    }

    public function test_url_is_null_when_not_configured(): void
    {
        config()->set('payguard.apk', ['url' => null, 'version' => null, 'date' => null]);
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->getJson('/api/panel/app-download');

        $response->assertOk();
        $response->assertJsonPath('data.url', null);
    }

    public function test_guest_cannot_access_app_download(): void
    {
        $this->getJson('/api/panel/app-download')->assertUnauthorized();
    }
}
