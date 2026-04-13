<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les claus retornades per Redis (KEYS) poden incloure el prefix de Laravel (config database.redis.options.prefix).
 * Els parsers de EventSeatHoldService han d’acceptar tant claus lògiques com físiques.
 */
class RedisSeatKeyParsePatternsTest extends TestCase
{
    public function test_seat_id_extracted_with_laravel_style_prefix (): void
    {
        $physical = 'laravel-database-event:2:seat:section_1-row_1-seat_21';
        $this->assertSame(1, preg_match('/event:\d+:seat:(.+)$/', $physical, $m));
        $this->assertSame('section_1-row_1-seat_21', $m[1]);
    }

    public function test_seat_id_extracted_without_prefix (): void
    {
        $logical = 'event:2:seat:section_1-row_1-seat_21';
        $this->assertSame(1, preg_match('/event:\d+:seat:(.+)$/', $logical, $m));
        $this->assertSame('section_1-row_1-seat_21', $m[1]);
    }

    public function test_user_index_event_id_with_prefix (): void
    {
        $physical = 'myapp-database-user:7:event:3:held_seats';
        $this->assertSame(1, preg_match('/user:\d+:event:(\d+):held_seats$/', $physical, $m));
        $this->assertSame('3', $m[1]);
    }
}
