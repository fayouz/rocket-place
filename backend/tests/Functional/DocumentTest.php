<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Documents" tab of a place: folders and files proxied to Rocket Cloud. Without ROCKET_CLOUD_URL/TOKEN in this
 * test environment, App\Cloud\DemoCloud answers (no network call), which also seeds a demo document per place.
 */
final class DocumentTest extends WebTestCase
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

    public function testListingCreatesTheFolderLazily(): void
    {
        $port = $this->seed('Le port');

        $this->api('GET', "/api/places/$port/documents");
        $this->assertStatus(401);

        $documents = $this->api('GET', "/api/places/$port/documents", null, $this->user);
        self::assertNotEmpty($documents['folderId']);
        self::assertCount(1, $documents['items']);
        self::assertSame('Bienvenue.pdf', $documents['items'][0]['name']);
        self::assertSame('file', $documents['items'][0]['kind']);
    }

    public function testOnlyAnAdminWrites(): void
    {
        $port = $this->seed('Le port');

        $this->api('POST', "/api/places/$port/documents/folders", ['name' => 'Contrats'], $this->user);
        $this->assertStatus(403);

        $created = $this->api('POST', "/api/places/$port/documents/folders", ['name' => 'Contrats'], $this->admin);
        $this->assertStatus(201);
        self::assertSame('folder', $created['kind']);
        self::assertSame('Contrats', $created['name']);

        $documents = $this->api('GET', "/api/places/$port/documents", null, $this->user);
        self::assertCount(2, $documents['items']);

        $this->api('DELETE', "/api/places/$port/documents/{$created['id']}", null, $this->user);
        $this->assertStatus(403);
        $this->api('DELETE', "/api/places/$port/documents/{$created['id']}", null, $this->admin);
        $this->assertStatus(200);

        $documents = $this->api('GET', "/api/places/$port/documents", null, $this->user);
        self::assertCount(1, $documents['items']);
    }

    public function testAFolderOfAnotherPlaceIs404(): void
    {
        $portId = $this->seed('Le port');
        $vignesId = $this->seed('Les vignes');

        $this->api('GET', "/api/places/$vignesId/documents", null, $this->admin);
        $foreignFolder = $this->api('POST', "/api/places/$vignesId/documents/folders", ['name' => 'Contrats'], $this->admin);

        $this->api('GET', "/api/places/$portId/documents?folder={$foreignFolder['id']}", null, $this->user);
        $this->assertStatus(404);

        $this->api('POST', "/api/places/$portId/documents/folders", ['name' => 'Sous-dossier', 'folder' => $foreignFolder['id']], $this->admin);
        $this->assertStatus(404);

        $this->api('PATCH', "/api/places/$portId/documents/{$foreignFolder['id']}", ['name' => 'Renommé'], $this->admin);
        $this->assertStatus(404);

        $this->api('DELETE', "/api/places/$portId/documents/{$foreignFolder['id']}", null, $this->admin);
        $this->assertStatus(404);
    }

    public function testAFileOfAnotherPlaceIs404(): void
    {
        $portId = $this->seed('Le port');
        $vignesId = $this->seed('Les vignes');

        $vignesDocuments = $this->api('GET', "/api/places/$vignesId/documents", null, $this->admin);
        $foreignFile = $vignesDocuments['items'][0]['id']; // demo-seeded "Bienvenue.pdf"

        $this->api('GET', "/api/places/$portId/documents/$foreignFile/content", null, $this->user);
        $this->assertStatus(404);

        $this->api('PATCH', "/api/places/$portId/documents/$foreignFile", ['name' => 'Renommé'], $this->admin);
        $this->assertStatus(404);

        $this->api('DELETE', "/api/places/$portId/documents/$foreignFile", null, $this->admin);
        $this->assertStatus(404);

        $portDocuments = $this->api('GET', "/api/places/$portId/documents", null, $this->admin);
        $ownFile = $portDocuments['items'][0]['id'];
        $this->api('PATCH', "/api/places/$portId/documents/$ownFile", ['folder' => $foreignFile], $this->admin);
        $this->assertStatus(404);
    }

    public function testThePlaceRootFolderCannotBeMovedOrDeleted(): void
    {
        $port = $this->seed('Le port');
        $documents = $this->api('GET', "/api/places/$port/documents", null, $this->admin);
        $rootFolder = 'folder:'.$documents['rootFolderId'];

        $this->api('PATCH', "/api/places/$port/documents/$rootFolder", ['folder' => null], $this->admin);
        $this->assertStatus(400);

        $this->api('DELETE', "/api/places/$port/documents/$rootFolder", null, $this->admin);
        $this->assertStatus(400);
    }

    /**
     * A "rocketcloud" connector on the place is picked over the legacy ROCKET_CLOUD_URL/TOKEN fallback
     * (App\Cloud\DocumentProviderRegistry): the connector's secret variable is not set in .env, so listing documents
     * fails instead of silently falling back to DemoCloud — proof that the connector was resolved.
     */
    public function testAPlaceConnectorIsPreferredOverTheLegacyRocketCloudAccount(): void
    {
        $port = $this->seed('Le port');
        $this->api('GET', "/api/places/$port/documents", null, $this->user);
        $this->assertStatus(200, 'no connector yet: legacy/demo client answers');

        $connector = $this->api('POST', "/api/places/$port/connectors", [
            'pluginId' => 'rocketcloud', 'config' => ['url' => 'https://cloud.example.org', 'secretVar' => 'CONNECTOR_ROCKET_CLOUD_TEST'],
        ], $this->admin);
        $this->assertStatus(201);

        $this->api('GET', "/api/places/$port/documents", null, $this->user);
        $this->assertStatus(400, 'the place own connector is used, and its secret is not configured');

        $this->api('PATCH', "/api/connectors/{$connector['id']}", ['enabled' => false], $this->admin);
        $this->api('GET', "/api/places/$port/documents", null, $this->user);
        $this->assertStatus(200, 'disabling the connector restores the legacy/demo fallback');
    }

    private function seed(string $name): string
    {
        return $this->api('POST', '/api/places', ['name' => $name], $this->admin)['id'];
    }
}
