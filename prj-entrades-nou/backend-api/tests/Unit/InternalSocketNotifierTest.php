<?php

namespace Tests\Unit;

use App\Services\Socket\InternalSocketNotifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Garanteix que la notificació HTTP cap al socket-server envia JSON (el node fa JSON.parse del cos).
 */
class InternalSocketNotifierTest extends TestCase
{
    public function test_emit_to_event_room_posts_json_with_held_payload(): void
    {
        Http::fake([
            'fake-socket.test/*' => Http::response('', 204),
        ]);

        config([
            'services.socket.internal_url' => 'http://fake-socket.test',
            'services.socket.internal_secret' => '',
        ]);

        /** @var InternalSocketNotifier $notifier */
        $notifier = app(InternalSocketNotifier::class);
        $notifier->emitToEventRoom('2', 'SeatStatusUpdated', [
            'eventId' => '2',
            'seatId' => 'section_1-row_1-seat_5',
            'status' => 'held',
            'userId' => '7',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $url = $request->url();
            if (! str_contains($url, 'fake-socket.test') || ! str_contains($url, '/internal/emit')) {
                return false;
            }
            $ct = $request->header('Content-Type');
            if ($ct === null || ! str_contains(implode('', $ct), 'application/json')) {
                return false;
            }
            $data = $request->data();
            if (($data['room'] ?? null) !== 'event:2') {
                return false;
            }
            if (($data['event'] ?? null) !== 'SeatStatusUpdated') {
                return false;
            }
            $pl = $data['payload'] ?? null;
            if (! is_array($pl)) {
                return false;
            }
            if (($pl['status'] ?? null) !== 'held') {
                return false;
            }
            if (($pl['seatId'] ?? null) !== 'section_1-row_1-seat_5') {
                return false;
            }

            return true;
        });
    }

    public function test_emit_to_event_room_posts_json_with_sold_payload(): void
    {
        Http::fake([
            'fake-socket.test/*' => Http::response('', 204),
        ]);

        config([
            'services.socket.internal_url' => 'http://fake-socket.test',
            'services.socket.internal_secret' => '',
        ]);

        /** @var InternalSocketNotifier $notifier */
        $notifier = app(InternalSocketNotifier::class);
        $notifier->emitToEventRoom('2', 'SeatStatusUpdated', [
            'eventId' => '2',
            'seatId' => 'section_1-row_1-seat_7',
            'status' => 'sold',
            'userId' => null,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $data = $request->data();
            $pl = $data['payload'] ?? [];

            return ($pl['status'] ?? null) === 'sold'
                && array_key_exists('userId', $pl)
                && $pl['userId'] === null;
        });
    }

    public function test_emit_skips_when_internal_url_empty(): void
    {
        Http::fake();

        config(['services.socket.internal_url' => '']);

        /** @var InternalSocketNotifier $notifier */
        $notifier = app(InternalSocketNotifier::class);
        $notifier->emitToEventRoom('1', 'SeatStatusUpdated', ['eventId' => '1', 'seatId' => 'x', 'status' => 'held', 'userId' => '1']);

        Http::assertNothingSent();
    }
}
