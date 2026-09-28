<?php

namespace App\Cleaning;

use App\Cloud\DocumentProviderRegistry;
use App\Entity\CleaningTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Carrying a cleaning out (status, checklist, notes, photos), shared by the signed-in API
 * (CleaningController) and the secret link without account (PublicCleaningController). Callers check who may act.
 */
final class CleaningWork
{
    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];
    private const PHOTO_MAX_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly DocumentProviderRegistry $documentProviders,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param array<string, mixed> $body "status", "notes", "checklist": [{"index": int, "done": bool}] (other keys ignored) */
    public function apply(CleaningTask $task, array $body): void
    {
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
    }

    /** Uploads a photo (jpeg/png/webp/heic, 15 Mo max) into the place's Rocket Cloud folder and records it on the cleaning. */
    public function addPhoto(CleaningTask $task, mixed $file, string $moment): void
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new HttpException(400, 'Aucune photo envoyée.');
        }
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
        $name = \sprintf('menage-%s-%s-%s.%s', $now->format('Ymd-His'), substr($task->getId()->toRfc4122(), -8), $moment, $extension);
        $renamed = new UploadedFile($file->getPathname(), $name, $file->getMimeType(), null, true);
        $place = $task->getPlace();
        $cloud = $this->documentProviders->providerFor($place);
        $folderId = $cloud->ensureFolder($place->getId()->toRfc4122(), $place->getCloudFolderId() ?? '', $place->getName());
        $place->setCloudFolderId($folderId);
        $item = $cloud->upload($folderId, $renamed);
        $task->addPhoto('file:'.$item['id'], $item['name'], $moment, $now);
    }

    /** Content of one of the cleaning's own photos (never any other file of the place). */
    public function photoContent(CleaningTask $task, string $fileId): string
    {
        if (!\in_array($fileId, array_column($task->getPhotos(), 'fileId'), true)) {
            throw new HttpException(404, 'Photo inconnue pour ce ménage.');
        }
        $place = $task->getPlace();

        return $this->documentProviders->providerFor($place)->content(substr($fileId, 5));
    }

}
