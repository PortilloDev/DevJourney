<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetRequestHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetRequestHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_forces_the_host_from_app_url(): void
    {
        config(['app.url' => 'http://localhost:8002']);

        $request = Request::create('http://localhost/livewire/upload-file', 'POST');

        (new SetRequestHost)->handle($request, fn (): Response => new Response('ok'));

        $this->assertSame('http://localhost:8002', $request->getSchemeAndHttpHost());
        $this->assertSame('http://localhost:8002/livewire/upload-file', $request->url());
    }

    public function test_it_leaves_a_mismatched_host_untouched(): void
    {
        config(['app.url' => 'http://localhost:8002']);

        $request = Request::create('http://example.com/livewire/upload-file', 'POST');

        (new SetRequestHost)->handle($request, fn (): Response => new Response('ok'));

        $this->assertSame('http://example.com/livewire/upload-file', $request->url());
    }

    public function test_a_signed_url_validates_even_with_a_portless_incoming_host(): void
    {
        config(['app.url' => 'http://localhost:8002']);

        Route::middleware(['web', 'signed'])
            ->get('/__test_signed', fn (): string => 'ok')
            ->name('__test.signed');

        Route::getRoutes()->refreshNameLookups();

        $url = URL::temporarySignedRoute('__test.signed', now()->addMinute());

        // Simulate a request that arrives with a portless Host.
        $this->get($url, ['Host' => 'localhost'])->assertOk()->assertSee('ok');
    }
}
