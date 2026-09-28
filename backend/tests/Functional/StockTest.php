<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Stock catalogue (admin-only writes) and per-place levels (any user may set the level, e.g. whoever cleans). */
final class StockTest extends WebTestCase
{
    use ApiTestTrait;

    private string $admin;
    private string $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $this->user = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
    }

    public function testCatalogueIsAdminOnly(): void
    {
        $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette', 'asin' => 'B07PGL7C4L', 'reorderQty' => 12], $this->user);
        $this->assertStatus(403);

        $item = $this->api('POST', '/api/stock-items', ['name' => 'Papier toilette', 'asin' => 'B07PGL7C4L', 'reorderQty' => 12], $this->admin);
        $this->assertStatus(201);
        self::assertSame('Papier toilette', $item['name']);

        $items = $this->api('GET', '/api/stock-items', null, $this->user);
        self::assertCount(1, $items);
    }

    public function testAnyUserSetsTheLevelOfATrackedItem(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Café dosettes', 'reorderQty' => 4, 'subscription' => true], $this->admin);

        $level = $this->api('POST', '/api/stock-levels', ['place' => "/api/places/$place", 'item' => "/api/stock-items/{$item['id']}", 'level' => 'ok'], $this->user);
        $this->assertStatus(201);
        self::assertSame('ok', $level['level']);

        $updated = $this->api('PATCH', "/api/stock-levels/{$level['id']}", ['level' => 'low'], $this->user);
        self::assertSame('low', $updated['level']);

        $levels = $this->api('GET', '/api/stock-levels?'.http_build_query(['place' => "/api/places/$place"]), null, $this->user);
        self::assertSame("/api/stock-items/{$item['id']}", $levels[0]['item'], 'relations are IRIs, never embedded');
        self::assertNotEmpty($levels);

        $this->api('DELETE', "/api/stock-levels/{$level['id']}", null, $this->user);
        $this->assertStatus(204);
    }
}
