<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_not_found_page_is_localized_and_does_not_show_exception_details(): void
    {
        $this->withHeader('Accept-Language', 'ar')
            ->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('الصفحة غير موجودة')
            ->assertDontSee('Symfony');
    }

    public function test_legacy_emarket_product_demo_route_is_not_available(): void
    {
        $this->get('/product-v2.html')
            ->assertNotFound()
            ->assertDontSee('Demo Store')
            ->assertDontSee('Bootstrap 3.3.5');
    }
}
