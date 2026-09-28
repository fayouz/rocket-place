<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A client application (e.g. Rocket PMS) calls Place server-to-server with its application token (Bearer rpl_…):
 * it may use Place's business endpoints (App\Security\PlaceAccessVoter, App\Security\PlaceScopeGuardListener) but
 * never rocket-core's administration (users, applications) nor connector secrets. No real lock is ever called here.
 */
final class ApplicationAccessTest extends WebTestCase
{
    use ApiTestTrait;

    public function testApplicationTokenDrivesPlacesLocksAndGrants(): void
    {
        $admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        [, $secret] = $this->createApplication(false, 'Rocket PMS');
        $app = 'Bearer '.$secret;

        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $app);
        $this->assertStatus(201);
        $this->api('GET', '/api/places', null, $app);
        $this->assertStatus(200);
        $this->api('GET', "/api/places/{$place['id']}/locks", null, $app);
        $this->assertStatus(200);

        // Lock configuration stays an administrator's job.
        // Stock moved to Rocket Stock: no stock route left here.
        $this->api('GET', '/api/stock-items', null, $admin);
        $this->assertStatus(404);
        $this->api('POST', '/api/locks/sync', [], $admin);
        $this->api('PUT', '/api/locks/90001', ['place' => $place['id']], $app);
        $this->assertStatus(403);
        $this->api('PUT', '/api/locks/90001', ['place' => $place['id']], $admin);

        $body = ['lockId' => 90001, 'label' => 'Sofia Rossi', 'validFrom' => '2026-10-01T15:00:00+02:00', 'validUntil' => '2026-10-05T11:00:00+02:00', 'externalRef' => 'booking-42'];
        $first = $this->api('POST', "/api/places/{$place['id']}/access-grants", $body, $app);
        $this->assertStatus(201);
        $again = $this->api('POST', "/api/places/{$place['id']}/access-grants", $body, $app);
        $this->assertStatus(200);
        self::assertSame($first['id'], $again['id']);
        self::assertSame($first['code'], $again['code']);
        self::assertCount(1, $this->api('GET', "/api/places/{$place['id']}/access-grants", null, $app));

        // Without externalRef every call plans a new grant.
        unset($body['externalRef']);
        $this->api('POST', "/api/places/{$place['id']}/access-grants", $body, $app);
        $this->assertStatus(201);
        self::assertCount(2, $this->api('GET', "/api/places/{$place['id']}/access-grants", null, $app));
    }

    public function testApplicationTokenCannotReachAdministrationOrSecrets(): void
    {
        [, $secret] = $this->createApplication(false, 'Rocket PMS');
        $app = 'Bearer '.$secret;
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $app);

        foreach (['/api/users', '/api/applications', "/api/places/{$place['id']}/connectors", '/api/plugins'] as $uri) {
            $this->api('GET', $uri, null, $app);
            $this->assertStatus(403);
        }
    }

    public function testImpersonatingApplicationKeepsTheUsersRoles(): void
    {
        $this->createUser('alice@example.org');
        [, $secret] = $this->createApplication(true);

        $this->api('GET', '/api/places', null, 'Bearer '.$secret, ['X-Impersonate-User' => 'alice@example.org']);
        $this->assertStatus(200);
        $this->api('POST', '/api/places', ['name' => 'Nope'], 'Bearer '.$secret, ['X-Impersonate-User' => 'alice@example.org']);
        $this->assertStatus(403);
    }
}
