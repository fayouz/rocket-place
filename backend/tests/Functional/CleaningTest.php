<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Cleanings ("ménage") of a place: planning by a manager or a client app, carried out by the assignee. No network (DemoCloud). */
final class CleaningTest extends WebTestCase
{
    use ApiTestTrait;

    private string $admin;
    private string $alice;
    private string $bob;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->admin = 'Bearer '.$this->jwtFor($this->createUser('admin@example.org', ['ROLE_ADMIN']));
        $this->alice = 'Bearer '.$this->jwtFor($this->createUser('alice@example.org'));
        $this->bob = 'Bearer '.$this->jwtFor($this->createUser('bob@example.org'));
    }

    public function testChecklistTemplateIsCopiedAndOnlyManagersPlan(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $this->api('PUT', "/api/places/$place/cleaning-checklist", ['items' => ['Draps changés', ' ', 'Poubelles']], $this->alice);
        $this->assertStatus(403);
        self::assertSame(['Draps changés', 'Poubelles'], $this->api('PUT', "/api/places/$place/cleaning-checklist", ['items' => ['Draps changés', ' ', 'Poubelles']], $this->admin));

        $this->api('POST', "/api/places/$place/cleanings", ['scheduledAt' => 'today 11:00'], $this->alice);
        $this->assertStatus(403);
        $task = $this->api('POST', "/api/places/$place/cleanings", ['scheduledAt' => (new \DateTimeImmutable('today 11:00'))->format(\DATE_ATOM), 'dueAt' => (new \DateTimeImmutable('today 16:00'))->format(\DATE_ATOM), 'assigneeEmail' => 'alice@example.org'], $this->admin);
        $this->assertStatus(201);
        self::assertSame('Ménage', $task['label']);
        self::assertSame('todo', $task['status']);
        self::assertSame('alice@example.org', $task['assignee']['email']);
        self::assertSame([['label' => 'Draps changés', 'done' => false], ['label' => 'Poubelles', 'done' => false]], $task['checklist']);

        $this->api('POST', "/api/places/$place/cleanings", ['scheduledAt' => 'nope'], $this->admin);
        $this->assertStatus(422);

        self::assertCount(1, $this->api('GET', '/api/cleanings?mine=1', null, $this->alice));
        self::assertCount(0, $this->api('GET', '/api/cleanings?mine=1', null, $this->bob));
        self::assertCount(1, $this->api('GET', "/api/places/$place/cleanings", null, $this->bob));
        self::assertCount(0, $this->api('GET', '/api/cleanings?date=2020-01-01', null, $this->bob));
    }

    public function testAssigneeCarriesItOutWithChecklistPhotoAndStock(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $this->api('PUT', "/api/places/$place/cleaning-checklist", ['items' => ['Salle de bain']], $this->admin);
        $item = $this->api('POST', '/api/stock-items', ['name' => 'Café', 'reorderQty' => 4], $this->admin);
        $level = $this->api('POST', '/api/stock-levels', ['place' => "/api/places/$place", 'item' => "/api/stock-items/{$item['id']}"], $this->admin);
        $this->assertStatus(201);
        $id = $this->api('POST', "/api/places/$place/cleanings", ['scheduledAt' => (new \DateTimeImmutable('today 10:00'))->format(\DATE_ATOM), 'assigneeEmail' => 'alice@example.org'], $this->admin)['id'];

        $this->api('PATCH', "/api/cleanings/$id", ['status' => 'in_progress'], $this->bob);
        $this->assertStatus(403);
        $this->api('PATCH', "/api/cleanings/$id", ['assigneeEmail' => 'bob@example.org'], $this->alice);
        $this->assertStatus(403);

        $task = $this->api('PATCH', "/api/cleanings/$id", ['status' => 'in_progress', 'checklist' => [['index' => 0, 'done' => true]], 'notes' => 'Tache sur le canapé'], $this->alice);
        $this->assertStatus(200);
        self::assertTrue($task['checklist'][0]['done']);
        self::assertNotNull($task['startedAt']);
        $this->api('PATCH', "/api/cleanings/$id", ['checklist' => [['index' => 5, 'done' => true]]], $this->alice);
        $this->assertStatus(422);

        $task = $this->api('POST', "/api/cleanings/$id/stock", ['stockLevelId' => $level['id'], 'level' => 'low'], $this->alice);
        $this->assertStatus(200);
        self::assertSame('low', $task['stockReports'][0]['level']);
        self::assertSame('low', $this->api('GET', "/api/stock-levels/{$level['id']}", null, $this->alice)['level']);

        $png = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->client->request('POST', "/api/cleanings/$id/photos", ['moment' => 'before'], ['file' => new UploadedFile($png, 'IMG_0001.png', 'image/png', null, true)], ['HTTP_AUTHORIZATION' => $this->alice, 'HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(201);
        $task = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('before', $task['photos'][0]['moment']);
        self::assertStringStartsWith('menage-', $task['photos'][0]['name']);
        $this->client->request('GET', "/api/places/$place/documents/{$task['photos'][0]['fileId']}/content", server: ['HTTP_AUTHORIZATION' => $this->admin]);
        $this->assertStatus(200);

        $txt = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($txt, 'not an image');
        $this->client->request('POST', "/api/cleanings/$id/photos", [], ['file' => new UploadedFile($txt, 'a.jpg', 'image/jpeg', null, true)], ['HTTP_AUTHORIZATION' => $this->alice, 'HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(422);

        $task = $this->api('PATCH', "/api/cleanings/$id", ['status' => 'done'], $this->alice);
        self::assertNotNull($task['completedAt']);
        self::assertFalse($task['late']);
    }

    public function testApplicationCreatesIdempotentlyByExternalRef(): void
    {
        [, $secret] = $this->createApplication(false, 'Rocket PMS');
        $app = 'Bearer '.$secret;
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $app)['id'];
        $body = ['scheduledAt' => '2026-10-05T11:00:00+02:00', 'dueAt' => '2026-10-05T16:00:00+02:00', 'label' => 'Ménage après Sofia Rossi', 'externalRef' => 'booking-42'];

        $first = $this->api('POST', "/api/places/$place/cleanings", $body, $app);
        $this->assertStatus(201);
        $again = $this->api('POST', "/api/places/$place/cleanings", $body, $app);
        $this->assertStatus(200);
        self::assertSame($first['id'], $again['id']);
        self::assertCount(1, $this->api('GET', "/api/places/$place/cleanings", null, $app));
        $this->api('GET', '/api/cleanings?date=2026-10-05', null, $app);
        $this->assertStatus(200);
        $this->api('PATCH', "/api/cleanings/{$first['id']}", ['dueAt' => '2026-10-05T17:00:00+02:00'], $app);
        $this->assertStatus(200);
        $this->api('DELETE', "/api/cleanings/{$first['id']}", null, $app);
        $this->assertStatus(200);
    }

    public function testLateCleaningsShowOnTodayAndDashboard(): void
    {
        $place = $this->api('POST', '/api/places', ['name' => 'Le port'], $this->admin)['id'];
        $this->api('POST', "/api/places/$place/cleanings", ['scheduledAt' => (new \DateTimeImmutable('-2 days 10:00'))->format(\DATE_ATOM)], $this->admin);
        $today = $this->api('GET', '/api/cleanings', null, $this->alice);
        self::assertCount(1, $today);
        self::assertTrue($today[0]['late']);

        $dashboard = $this->api('GET', '/api/dashboard', null, $this->admin);
        $this->assertStatus(200);
        self::assertStringContainsString('cleanings_late', (string) json_encode($dashboard));
    }
}
