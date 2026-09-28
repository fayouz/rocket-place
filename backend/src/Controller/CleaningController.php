<?php

namespace App\Controller;

use App\Cloud\DocumentProviderRegistry;
use App\Entity\CleaningChecklistItem;
use App\Entity\CleaningTask;
use App\Entity\Place;
use App\Entity\StockLevel;
use App\Repository\CleaningChecklistItemRepository;
use App\Repository\CleaningTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Entity\User;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Cleanings ("ménage") of places. Planning (create, reschedule, assign, delete) and the checklist template are
 * PLACE_MANAGE (an administrator, or a client application such as a PMS acting for itself); carrying a cleaning out
 * (status, checklist, notes, photos, stock) is open to its assignee — or anyone when unassigned — and to managers.
 */
#[IsGranted('PLACE_READ')]
final class CleaningController extends AbstractController
{
    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];
    private const PHOTO_MAX_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly CleaningTaskRepository $tasks,
        private readonly CleaningChecklistItemRepository $checklistItems,
        private readonly DocumentProviderRegistry $documentProviders,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Query: date=YYYY-MM-DD (default today, "all" for no date filter), mine=1, place=<uuid>. Late open tasks are included with a date. */
    #[Route('/api/cleanings', name: 'api_cleanings', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        [$from, $to] = $this->day((string) $request->query->get('date', ''));
        $place = null;
        if ('' !== ($placeId = (string) $request->query->get('place', ''))) {
            $place = Uuid::isValid($placeId) ? $this->em->find(Place::class, Uuid::fromString($placeId)) : null;
            $place ?? throw new HttpException(404, 'Lieu inconnu.');
        }
        $assignee = null;
        if ($request->query->getBoolean('mine')) {
            $assignee = $this->getUser() instanceof User ? $this->getUser() : throw new HttpException(400, '« mine » demande un utilisateur.');
        }
        $now = new \DateTimeImmutable();

        return $this->json(array_map(static fn (CleaningTask $t) => $t->toArray($now), $this->tasks->search($from, $to, $place, $assignee, null !== $from)));
    }

    #[Route('/api/places/{id}/cleanings', name: 'api_place_cleanings', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function listForPlace(#[MapEntity] Place $place): JsonResponse
    {
        $now = new \DateTimeImmutable();

        return $this->json(array_map(static fn (CleaningTask $t) => $t->toArray($now), $this->tasks->search(null, null, $place)));
    }

    /**
     * JSON {"scheduledAt": ISO-8601, "dueAt"?: ISO-8601|null, "label"?: string, "assigneeEmail"?|"assigneeId"?: string,
     * "notes"?: string, "externalRef"?: string}. Find-or-create by externalRef: 200 with the existing task (unchanged).
     */
    #[Route('/api/places/{id}/cleanings', name: 'api_place_cleanings_create', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function create(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $externalRef = null === ($body['externalRef'] ?? null) ? null : mb_substr(trim((string) $body['externalRef']), 0, 120);
        if (null !== $externalRef && null !== ($existing = $this->tasks->findOneBy(['place' => $place, 'externalRef' => $externalRef]))) {
            return $this->json($existing->toArray());
        }
        $label = trim((string) ($body['label'] ?? '')) ?: 'Ménage';
        $checklist = array_map(static fn (CleaningChecklistItem $i) => $i->getLabel(), $this->checklistItems->findBy(['place' => $place], ['position' => 'ASC']));
        $task = new CleaningTask($place, mb_substr($label, 0, 120), $this->date($body['scheduledAt'] ?? null) ?? throw new HttpException(422, 'scheduledAt requis.'), $checklist, $externalRef);
        $this->applyPlanning($task, $body);
        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task->toArray(), 201);
    }

    #[Route('/api/cleanings/{id}', name: 'api_cleaning', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function show(#[MapEntity] CleaningTask $task): JsonResponse
    {
        return $this->json($task->toArray());
    }

    /**
     * JSON, all optional. Anyone doing the cleaning: "status", "notes", "checklist": [{"index": int, "done": bool}].
     * Managers only: "label", "scheduledAt", "dueAt", "assigneeEmail"/"assigneeId" (null to unassign).
     */
    #[Route('/api/cleanings/{id}', name: 'api_cleaning_update', methods: ['PATCH'], requirements: ['id' => Requirement::UUID])]
    public function update(#[MapEntity] CleaningTask $task, Request $request): JsonResponse
    {
        $this->assertCanWork($task);
        $body = $request->toArray();
        if (array_intersect(['label', 'scheduledAt', 'dueAt', 'assigneeEmail', 'assigneeId'], array_keys($body))) {
            $this->denyAccessUnlessGranted('PLACE_MANAGE');
            if (isset($body['label']) && '' !== trim((string) $body['label'])) {
                $task->setLabel(mb_substr(trim((string) $body['label']), 0, 120));
            }
            if (isset($body['scheduledAt'])) {
                $task->setScheduledAt($this->date($body['scheduledAt']) ?? throw new HttpException(422, 'scheduledAt invalide.'));
            }
            $this->applyPlanning($task, $body);
        }
        if (\array_key_exists('status', $body)) {
            if (!\in_array($body['status'], CleaningTask::STATUSES, true)) {
                throw new HttpException(422, 'Statut invalide ('.implode(', ', CleaningTask::STATUSES).').');
            }
            $task->setStatus($body['status'], new \DateTimeImmutable());
        }
        if (\array_key_exists('notes', $body)) {
            $notes = trim((string) $body['notes']);
            $task->setNotes('' === $notes ? null : mb_substr($notes, 0, 5000));
        }
        foreach ((array) ($body['checklist'] ?? []) as $check) {
            try {
                $task->checkItem((int) ($check['index'] ?? -1), (bool) ($check['done'] ?? false));
            } catch (\OutOfRangeException $e) {
                throw new HttpException(422, $e->getMessage());
            }
        }
        $this->em->flush();

        return $this->json($task->toArray());
    }

    #[Route('/api/cleanings/{id}', name: 'api_cleaning_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function delete(#[MapEntity] CleaningTask $task): JsonResponse
    {
        $this->em->remove($task);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    /** Multipart: "file" (jpeg/png/webp/heic, 15 Mo max), "moment" (before|after|damage). Stored in the place's Rocket Cloud folder. */
    #[Route('/api/cleanings/{id}/photos', name: 'api_cleaning_photo', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    public function photo(#[MapEntity] CleaningTask $task, Request $request): JsonResponse
    {
        $this->assertCanWork($task);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new HttpException(400, 'Aucune photo envoyée.');
        }
        $moment = (string) $request->request->get('moment', 'after');
        if (!\in_array($moment, CleaningTask::PHOTO_MOMENTS, true)) {
            throw new HttpException(422, 'Moment invalide ('.implode(', ', CleaningTask::PHOTO_MOMENTS).').');
        }
        $extension = self::PHOTO_TYPES[(string) $file->getMimeType()] ?? throw new HttpException(422, 'Seules les images (jpeg, png, webp, heic) sont acceptées.');
        if ($file->getSize() > self::PHOTO_MAX_BYTES) {
            throw new HttpException(422, 'Photo trop lourde (15 Mo au plus).');
        }
        if (\count($task->getPhotos()) >= CleaningTask::MAX_PHOTOS) {
            throw new HttpException(422, 'Nombre maximal de photos atteint pour ce ménage.');
        }
        $now = new \DateTimeImmutable();
        // Generated name, never the phone's: when, which cleaning, which moment.
        $name = sprintf('menage-%s-%s-%s.%s', $now->format('Ymd-His'), substr($task->getId()->toRfc4122(), -8), $moment, $extension);
        $renamed = new UploadedFile($file->getPathname(), $name, $file->getMimeType(), null, true);
        $place = $task->getPlace();
        $cloud = $this->documentProviders->providerFor($place);
        $folderId = $cloud->ensureFolder($place->getId()->toRfc4122(), $place->getCloudFolderId() ?? '', $place->getName());
        $place->setCloudFolderId($folderId);
        $item = $cloud->upload($folderId, $renamed);
        $task->addPhoto('file:'.$item['id'], $item['name'], $moment, $now);
        $this->em->flush();

        return $this->json($task->toArray(), 201);
    }

    /** JSON {"stockLevelId": uuid, "level": ok|low|empty}: sets the place's stock level and records it on the cleaning. */
    #[Route('/api/cleanings/{id}/stock', name: 'api_cleaning_stock', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    public function stock(#[MapEntity] CleaningTask $task, Request $request): JsonResponse
    {
        $this->assertCanWork($task);
        $body = $request->toArray();
        $levelId = (string) ($body['stockLevelId'] ?? '');
        $level = Uuid::isValid($levelId) ? $this->em->find(StockLevel::class, Uuid::fromString($levelId)) : null;
        if (null === $level || $level->getPlace() !== $task->getPlace()) {
            throw new HttpException(404, 'Article de stock inconnu pour ce lieu.');
        }
        if (!\in_array($body['level'] ?? null, StockLevel::LEVELS, true)) {
            throw new HttpException(422, 'Niveau invalide ('.implode(', ', StockLevel::LEVELS).').');
        }
        $level->setLevel($body['level']);
        $task->addStockReport($level, new \DateTimeImmutable());
        $this->em->flush();

        return $this->json($task->toArray());
    }

    #[Route('/api/places/{id}/cleaning-checklist', name: 'api_place_cleaning_checklist', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function checklist(#[MapEntity] Place $place): JsonResponse
    {
        return $this->json($this->checklistView($place));
    }

    /** JSON {"items": [string, ...]}: replaces the template (existing tasks keep their own copy). */
    #[Route('/api/places/{id}/cleaning-checklist', name: 'api_place_cleaning_checklist_update', methods: ['PUT'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function updateChecklist(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $labels = array_values(array_filter(array_map(static fn ($l) => mb_substr(trim((string) $l), 0, 160), (array) ($request->toArray()['items'] ?? [])), static fn (string $l) => '' !== $l));
        if (\count($labels) > 100) {
            throw new HttpException(422, '100 points au plus.');
        }
        foreach ($this->checklistItems->findBy(['place' => $place]) as $item) {
            $this->em->remove($item);
        }
        foreach ($labels as $i => $label) {
            $this->em->persist(new CleaningChecklistItem($place, $label, $i));
        }
        $this->em->flush();

        return $this->json($this->checklistView($place));
    }

    /** @return list<string> */
    private function checklistView(Place $place): array
    {
        return array_map(static fn (CleaningChecklistItem $i) => $i->getLabel(), $this->checklistItems->findBy(['place' => $place], ['position' => 'ASC']));
    }

    /** @param array<string, mixed> $body */
    private function applyPlanning(CleaningTask $task, array $body): void
    {
        if (\array_key_exists('dueAt', $body)) {
            $due = null === $body['dueAt'] ? null : ($this->date($body['dueAt']) ?? throw new HttpException(422, 'dueAt invalide.'));
            if (null !== $due && $due < $task->getScheduledAt()) {
                throw new HttpException(422, 'dueAt doit suivre scheduledAt.');
            }
            $task->setDueAt($due);
        }
        if (\array_key_exists('assigneeEmail', $body) || \array_key_exists('assigneeId', $body)) {
            $email = $body['assigneeEmail'] ?? null;
            $id = $body['assigneeId'] ?? null;
            $user = null;
            if (null !== $email && '' !== $email) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim((string) $email))]) ?? throw new HttpException(422, 'Utilisateur inconnu.');
            } elseif (null !== $id && '' !== $id) {
                $user = (Uuid::isValid((string) $id) ? $this->em->find(User::class, Uuid::fromString((string) $id)) : null) ?? throw new HttpException(422, 'Utilisateur inconnu.');
            }
            $task->setAssignee($user);
        }
        if (\array_key_exists('notes', $body) && null === $task->getNotes() && '' !== trim((string) $body['notes'])) {
            $task->setNotes(mb_substr(trim((string) $body['notes']), 0, 5000));
        }
    }

    /** A non-manager may only work on cleanings assigned to them, or not assigned at all. */
    private function assertCanWork(CleaningTask $task): void
    {
        if ($this->isGranted('PLACE_MANAGE') || null === $task->getAssignee() || $task->getAssignee() === $this->getUser()) {
            return;
        }
        throw new HttpException(403, 'Ce ménage est attribué à quelqu’un d’autre.');
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} */
    private function day(string $date): array
    {
        if ('all' === $date) {
            return [null, null];
        }
        $from = '' === $date ? new \DateTimeImmutable('today') : \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $from) {
            throw new HttpException(422, 'date attendue au format AAAA-MM-JJ.');
        }

        return [$from, $from->modify('+1 day')];
    }
}
