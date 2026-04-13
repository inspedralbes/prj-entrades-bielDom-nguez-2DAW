<?php

namespace Tests\Feature;

use App\Services\Ticket\SocketTicketSvgClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocketTicketSvgClientTest extends TestCase
{
    public function test_svg_for_qr_payload_posts_to_internal_qr_svg(): void
    {
        Http::fake([
            'http://socket.test/internal/qr-svg' => Http::response(
                '<svg xmlns="http://www.w3.org/2000/svg"><path /></svg>',
                200,
                ['Content-Type' => 'image/svg+xml'],
            ),
        ]);

        config(['services.socket.internal_url' => 'http://socket.test']);
        config(['services.socket.internal_secret' => 'secret']);

        $client = app(SocketTicketSvgClient::class);
        $svg = $client->svgForQrPayload('jwt-or-public-ref');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', (string) $svg);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/internal/qr-svg')
                && $request->hasHeader('X-Internal-Secret', 'secret')
                && ($request->data()['text'] ?? null) === 'jwt-or-public-ref';
        });
    }

    public function test_returns_null_when_no_internal_url(): void
    {
        config(['services.socket.internal_url' => '']);

        $client = app(SocketTicketSvgClient::class);
        $this->assertNull($client->svgForQrPayload('x'));
    }

    public function test_returns_null_for_empty_text(): void
    {
        config(['services.socket.internal_url' => 'http://socket.test']);
        Http::fake();

        $client = app(SocketTicketSvgClient::class);
        $this->assertNull($client->svgForQrPayload(''));
    }
}
