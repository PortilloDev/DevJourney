<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\PrometheusBasicAuth;
use Illuminate\Http\Request;
use Tests\TestCase;

class PrometheusBasicAuthTest extends TestCase
{
    public function test_refuses_in_non_local_when_credentials_are_empty(): void
    {
        config([
            'prometheus.basic_auth.username' => '',
            'prometheus.basic_auth.password' => '',
        ]);

        $response = (new PrometheusBasicAuth)->handle(
            Request::create('/prometheus'),
            fn () => response('ok'),
        );

        $this->assertSame(401, $response->status());
        $this->assertSame('Basic realm="prometheus"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_passes_through_in_local_when_credentials_are_empty(): void
    {
        config([
            'prometheus.basic_auth.username' => '',
            'prometheus.basic_auth.password' => '',
        ]);

        app()['env'] = 'local';

        $called = false;

        $response = (new PrometheusBasicAuth)->handle(
            Request::create('/prometheus'),
            function () use (&$called) {
                $called = true;

                return response('ok');
            },
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
    }

    public function test_rejects_requests_without_authorization_header(): void
    {
        config([
            'prometheus.basic_auth.username' => 'prometheus',
            'prometheus.basic_auth.password' => 'secret',
        ]);

        $response = (new PrometheusBasicAuth)->handle(
            Request::create('/prometheus'),
            fn () => response('ok'),
        );

        $this->assertSame(401, $response->status());
        $this->assertSame('Basic realm="prometheus"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_rejects_wrong_credentials(): void
    {
        config([
            'prometheus.basic_auth.username' => 'prometheus',
            'prometheus.basic_auth.password' => 'secret',
        ]);

        $request = Request::create('/prometheus');
        $request->headers->set('Authorization', 'Basic '.base64_encode('prometheus:wrong'));

        $response = (new PrometheusBasicAuth)->handle($request, fn () => response('ok'));

        $this->assertSame(401, $response->status());
    }

    public function test_allows_correct_credentials(): void
    {
        config([
            'prometheus.basic_auth.username' => 'prometheus',
            'prometheus.basic_auth.password' => 'secret',
        ]);

        $request = Request::create('/prometheus');
        $request->headers->set('Authorization', 'Basic '.base64_encode('prometheus:secret'));

        $called = false;

        $response = (new PrometheusBasicAuth)->handle(
            $request,
            function () use (&$called) {
                $called = true;

                return response('ok');
            },
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
    }
}
