<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Rocket\Core\Secrets\SecretVault;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Plugin catalogue, connectors (admin-only writes) and the read-only "Domotique" tab. No real Homey or web service is
 * ever called here: connectors without a configured address fall back to demo devices (App\Homey\DemoHomey).
 */
final class DomotiqueTest extends WebTestCase
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

    public function testCatalogue(): void
    {
        $this->api('GET', '/api/plugins');
        $this->assertStatus(401);
        $plugins = $this->api('GET', '/api/plugins', null, $this->user);
        self::assertSame(['homey', 'home_assistant', 'nuki', 'rocketcloud', 'webservice'], array_column($plugins, 'id'));
    }

    public function testOnlyAnAdminManagesConnectors(): void
    {
        $port = $this->seed();
        $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'webservice', 'name' => 'API météo', 'config' => ['baseUrl' => 'https://example.org']], $this->user);
        $this->assertStatus(403);

        $created = $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'webservice', 'name' => 'API météo', 'config' => ['baseUrl' => 'https://example.org']], $this->admin);
        $this->assertStatus(201);
        self::assertSame('webservice', $created['pluginId']);
        self::assertSame(['baseUrl' => 'https://example.org'], $created['config']);

        // Missing required field
        $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'webservice', 'config' => []], $this->admin);
        $this->assertStatus(400);

        $id = $created['id'];
        $updated = $this->api('PATCH', "/api/connectors/$id", ['enabled' => false], $this->admin);
        self::assertFalse($updated['enabled']);
        $this->api('PATCH', "/api/connectors/$id", ['enabled' => true], $this->user);
        $this->assertStatus(403);

        $this->api('DELETE', "/api/connectors/$id", null, $this->user);
        $this->assertStatus(403);
        $this->api('DELETE', "/api/connectors/$id", null, $this->admin);
        $this->assertStatus(200);
        self::assertCount(0, $this->api('GET', "/api/places/$port/connectors", null, $this->user));
    }

    public function testHomeyConnectorAndDomotiqueTab(): void
    {
        $port = $this->seed();
        $created = $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'homey', 'name' => 'Homey (démo)'], $this->admin);
        $this->assertStatus(201);

        $domotique = $this->api('GET', "/api/places/$port/domotique", null, $this->user);
        self::assertCount(1, $domotique['sections']);
        self::assertNull($domotique['sections'][0]['error']);
        self::assertNotEmpty($domotique['sections'][0]['cards']);
        self::assertSame('Salon - thermostat', $domotique['sections'][0]['cards'][0]['title']);

        $result = $this->api('POST', "/api/connectors/{$created['id']}/test", null, $this->admin);
        self::assertStringContainsString('appareil', $result['result']);
    }

    public function testConnectorSecretIsNeverStoredNorExposed(): void
    {
        $port = $this->seed();
        $created = $this->api('POST', "/api/places/$port/connectors", [
            'pluginId' => 'homey', 'name' => 'Homey salon',
            'config' => ['homeyUrl' => 'http://192.168.1.20', 'secret' => 'homey.salon.api_key'],
        ], $this->admin);
        $this->assertStatus(201);
        self::assertSame('homey.salon.api_key', $created['config']['secret'], 'only the name of the vault secret is stored, never the value');
        self::assertFalse($created['secrets']['secret'], 'the secret is not in the vault yet');

        static::getContainer()->get(SecretVault::class)->set('homey.salon.api_key', 'homey-local-key-123456');
        $shown = $this->api('GET', "/api/places/$port/connectors", null, $this->admin);
        self::assertTrue($shown[0]['secrets']['secret'], 'the secret is now in the vault');
        self::assertStringNotContainsString('homey-local-key', (string) $this->client->getResponse()->getContent(), 'the value is never exposed');

        // Public address rejected for a local Homey
        $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'homey', 'config' => ['homeyUrl' => 'https://example.org']], $this->admin);
        $this->assertStatus(400);

        // A connector can never use the integration secrets of the app itself (e.g. the Rocket Mailer token), nor an invalid name.
        foreach (['rocket.mailer.token', 'nuki.api_token', 'bad name'] as $name) {
            $this->api('POST', "/api/places/$port/connectors", ['pluginId' => 'homey', 'config' => ['secret' => $name]], $this->admin);
            $this->assertStatus(400);
        }
    }

    /** Demo place; returns its id. */
    private function seed(): string
    {
        return $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
    }
}
