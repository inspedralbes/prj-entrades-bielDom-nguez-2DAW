<?php

namespace Tests\Unit;

//================================ NAMESPACES / IMPORTS ============

use App\Services\Ticketmaster\Mapping\TicketmasterDiscoveryEventMapper;
use Tests\TestCase;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class TicketmasterSyncTest extends TestCase
{
    private function mapper(): TicketmasterDiscoveryEventMapper
    {
        return new TicketmasterDiscoveryEventMapper;
    }

    public function test_extract_and_map_category_filters_museums(): void
    {
        $eventWithMuseum = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Arts'],
                    'genre' => ['name' => 'Museum'],
                ],
            ],
        ];

        $result = $this->mapper()->extractAndMapCategory($eventWithMuseum);
        $this->assertNull($result);
    }

    public function test_extract_and_map_category_maps_dj_genres(): void
    {
        $eventWithDJ = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Music'],
                    'genre' => ['name' => 'DJ'],
                ],
            ],
        ];

        $result = $this->mapper()->extractAndMapCategory($eventWithDJ);
        $this->assertEquals('DJ', $result);
    }

    public function test_extract_and_map_category_maps_concerts(): void
    {
        $eventWithConcert = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Music'],
                ],
            ],
        ];

        $result = $this->mapper()->extractAndMapCategory($eventWithConcert);
        $this->assertEquals('Concert', $result);
    }

    public function test_extract_poster_image_url_picks_largest_dimensions(): void
    {
        $event = [
            'images' => [
                ['url' => 'https://cdn.example/small.jpg', 'width' => 100, 'height' => 50],
                ['url' => 'https://cdn.example/large.jpg', 'width' => 640, 'height' => 360],
            ],
        ];

        $result = $this->mapper()->extractPosterImageUrl($event);
        $this->assertSame('https://cdn.example/large.jpg', $result);
    }

    public function test_extract_poster_image_url_returns_null_without_images(): void
    {
        $this->assertNull($this->mapper()->extractPosterImageUrl([]));
    }

    public function test_extract_tm_url_from_url_field(): void
    {
        $event = [
            'url' => 'https://www.ticketmaster.com/event/123',
        ];

        $result = $this->mapper()->extractTmUrl($event);
        $this->assertEquals('https://www.ticketmaster.com/event/123', $result);
    }

    public function test_is_large_event_returns_true_when_no_capacity_info(): void
    {
        $event = [];

        $result = $this->mapper()->isLargeEvent($event);
        $this->assertTrue($result);
    }

    public function test_is_large_event_returns_false_for_small_venue(): void
    {
        $event = [
            '_embedded' => [
                'venues' => [
                    ['capacity' => 100],
                ],
            ],
        ];

        $result = $this->mapper()->isLargeEvent($event);
        $this->assertFalse($result);
    }

    public function test_is_large_event_returns_true_for_large_venue(): void
    {
        $event = [
            '_embedded' => [
                'venues' => [
                    ['capacity' => 1000],
                ],
            ],
        ];

        $result = $this->mapper()->isLargeEvent($event);
        $this->assertTrue($result);
    }
}
