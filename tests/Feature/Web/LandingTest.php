<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class LandingTest extends TestCase
{
    public function test_homepage_shows_the_landing(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Cada pago, verificado.');
        $response->assertSee('/app/iniciar-sesion');
        $response->assertSee('/app/crear-cuenta');
    }

    public function test_homepage_shows_download_link_when_apk_configured(): void
    {
        config()->set('payguard.apk.url', 'https://payguard.micaleth.com/apk/payguard.apk');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Descargar app');
        $response->assertSee('https://payguard.micaleth.com/apk/payguard.apk', false);
    }

    public function test_homepage_hides_download_link_without_apk_url(): void
    {
        config()->set('payguard.apk.url', null);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Descargar app');
    }
}
