<?php

namespace Tests\Unit;

use App\Services\Ticketmaster\TicketmasterEventImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketmasterSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_and_map_category_filters_museums (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractAndMapCategory');
        $method->setAccessible(true);

        $eventWithMuseum = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Arts'],
                    'genre' => ['name' => 'Museum'],
                ],
            ],
        ];

        $result = $method->invoke($service, $eventWithMuseum);
        $this->assertNull($result);
    }

    public function test_extract_and_map_category_maps_dj_genres (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractAndMapCategory');
        $method->setAccessible(true);

        $eventWithDJ = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Music'],
                    'genre' => ['name' => 'DJ'],
                ],
            ],
        ];

        $result = $method->invoke($service, $eventWithDJ);
        $this->assertEquals('DJ', $result);
    }

    public function test_extract_and_map_category_maps_concerts (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractAndMapCategory');
        $method->setAccessible(true);

        $eventWithConcert = [
            'classifications' => [
                [
                    'segment' => ['name' => 'Music'],
                ],
            ],
        ];

        $result = $method->invoke($service, $eventWithConcert);
        $this->assertEquals('Music', $result);
    }

    public function test_extract_poster_image_url_picks_largest_dimensions (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractPosterImageUrl');
        $method->setAccessible(true);

        $event = [
            'images' => [
                ['url' => 'https://cdn.example/small.jpg', 'width' => 100, 'height' => 50],
                ['url' => 'https://cdn.example/large.jpg', 'width' => 640, 'height' => 360],
            ],
        ];

        $result = $method->invoke($service, $event);
        $this->assertSame('https://cdn.example/large.jpg', $result);
    }

    public function test_extract_poster_image_url_returns_null_without_images (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractPosterImageUrl');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, []));
    }

    public function test_extract_tm_url_from_url_field (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractTmUrl');
        $method->setAccessible(true);

        $event = [
            'url' => 'https://www.ticketmaster.com/event/123',
        ];

        $result = $method->invoke($service, $event);
        $this->assertEquals('https://www.ticketmaster.com/event/123', $result);
    }

    public function test_is_large_event_returns_true_when_no_capacity_info (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isLargeEvent');
        $method->setAccessible(true);

        $event = [];

        $result = $method->invoke($service, $event);
        $this->assertTrue($result);
    }

    public function test_is_large_event_returns_false_for_small_venue (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isLargeEvent');
        $method->setAccessible(true);

        $event = [
            '_embedded' => [
                'venues' => [
                    ['capacity' => 100],
                ],
            ],
        ];

        $result = $method->invoke($service, $event);
        $this->assertFalse($result);
    }

    public function test_is_large_event_returns_true_for_large_venue (): void
    {
        $service = $this->getMockBuilder(TicketmasterEventImportService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isLargeEvent');
        $method->setAccessible(true);

        $event = [
            '_embedded' => [
                'venues' => [
                    ['capacity' => 1000],
                ],
            ],
        ];

        $result = $method->invoke($service, $event);
        $this->assertTrue($result);
    }
}