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
}
