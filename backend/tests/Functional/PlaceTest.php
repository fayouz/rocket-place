<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Places: CRUD (admin-only writes), locks sync/link, and generic access grants (plan/send/revoke). */
final class PlaceTest extends WebTestCase
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

    public function testOnlyAnAdminCreatesAndEditsPlaces(): void
    {
        $this->api('GET', '/api/places');
        $this->assertStatus(401);
        $this->api('POST', '/api/places', ['name' => 'Le port'], $this->user);
        $this->assertStatus(403);

        $port = $this->api('POST', '/api/places', ['name' => 'Le port', 'address' => '12 quai des Pêcheurs'], $this->admin);
        $this->assertStatus(201);
        self::assertSame('Le port', $port['name']);

        $places = $this->api('GET', '/api/places', null, $this->user);
        self::assertSame(['Le port'], array_column($places, 'name'));

        $id = $port['id'];
        $this->api('PATCH', "/api/places/$id", ['color' => 'green'], $this->user);
        $this->assertStatus(403);
        self::assertSame('green', $this->api('PATCH', "/api/places/$id", ['color' => 'green'], $this->admin)['color']);
        $this->api('PATCH', "/api/places/$id", ['color' => 'fuchsia'], $this->admin);
        $this->assertStatus(422);
    }

    public function testLockSyncAndLink(): void
    {
        $port = $this->createPlace('Le port');

        $this->api('POST', '/api/locks/sync', [], $this->user);
        $this->assertStatus(403);
        self::assertSame(['created' => 2], $this->api('POST', '/api/locks/sync', [], $this->admin));
        self::assertSame(['created' => 0], $this->api('POST', '/api/locks/sync', [], $this->admin));

        $locks = $this->api('GET', '/api/locks', null, $this->user)['locks'];
        self::assertSame([90001, 90002], array_column($locks, 'id'));

        $this->api('PUT', '/api/locks/90001', ['place' => $port], $this->user);
        $this->assertStatus(403);
        $this->api('PUT', '/api/locks/90001', ['place' => $port], $this->admin);
        $this->assertStatus(200);

        $ofPlace = $this->api('GET', "/api/places/$port/locks", null, $this->user)['locks'];
        self::assertSame([90001], array_column($ofPlace, 'id'));
    }

    public function testAccessGrantLifecycle(): void
    {
        $port = $this->createPlace('Le port');
        $this->api('POST', '/api/locks/sync', [], $this->admin);
        $this->api('PUT', '/api/locks/90001', ['place' => $port], $this->admin);

        $this->api('GET', "/api/places/$port/access-grants", null, $this->user);
        $this->assertStatus(200);

        $body = ['lockId' => 90001, 'label' => 'Sofia Rossi', 'validFrom' => '2026-10-01T15:00:00+02:00', 'validUntil' => '2026-10-05T11:00:00+02:00', 'externalRef' => 'booking-42'];
        $this->api('POST', "/api/places/$port/access-grants", $body, $this->user);
        $this->assertStatus(403);
        $grant = $this->api('POST', "/api/places/$port/access-grants", $body, $this->admin);
        $this->assertStatus(201);
        self::assertSame('planned', $grant['status']);
        self::assertMatchesRegularExpression('/^[1-9]{6}$/', $grant['code']);
        self::assertSame('booking-42', $grant['externalRef']);

        $list = $this->api('GET', "/api/places/$port/access-grants", null, $this->user);
        self::assertCount(1, $list);

        // Sent in demo mode (no secret nuki.api_token): rejected, never silently accepted.
        $this->api('POST', "/api/access-grants/{$grant['id']}/send", null, $this->admin);
        $this->assertStatus(400);

        $revoked = $this->api('POST', "/api/access-grants/{$grant['id']}/revoke", null, $this->admin);
        self::assertSame('revoked', $revoked['status']);

        // Invalid dates are rejected before anything is persisted.
        $bad = $body;
        $bad['validUntil'] = $bad['validFrom'];
        $this->api('POST', "/api/places/$port/access-grants", $bad, $this->admin);
        $this->assertStatus(422);
    }

    public function testDashboardShowsCountsAndAlerts(): void
    {
        $this->createPlace('Le port');
        $dashboard = $this->api('GET', '/api/dashboard', null, $this->admin);
        $kpis = array_column($dashboard['kpis'], 'value', 'id');
        self::assertSame(1, $kpis['places']);
    }

    private function createPlace(string $name): string
    {
        return $this->api('POST', '/api/places', ['name' => $name], $this->admin)['id'];
    }
}
