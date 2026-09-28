<?php

namespace App\Controller;

use App\Cloud\DocumentProviderInterface;
use App\Cloud\DocumentProviderRegistry;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Documents" tab of a place: folders and files kept in Rocket Cloud, one folder per place (Place::$cloudFolderId,
 * created on first use). Every call is scoped to that folder and proxied here, so a PMS user never needs a Rocket Cloud
 * account or token; only an admin can write. Item ids exposed to the front are "folder:<id>" / "file:<id>" so
 * @rocket/file-explorer can tell them apart without the app knowing Rocket Cloud's own item shape.
 */
#[IsGranted('PLACE_READ')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentProviderRegistry $documentProviders,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/places/{id}/documents', name: 'api_place_documents', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function list(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $cloud = $this->documentProviders->providerFor($place);
        $rootId = $this->folderOf($cloud, $place);
        // Optional "folder=folder:<id>" to browse into a subfolder created under the place's own folder.
        $requested = (string) $request->query->get('folder', '');
        $folderId = '' !== $requested ? $this->splitItemId($requested)[1] : $rootId;
        if ('' !== $requested) {
            $this->assertInTree($cloud, $rootId, 'folder', $folderId);
        }

        return $this->json(['folderId' => $folderId, 'rootFolderId' => $rootId, 'items' => array_map($this->view(...), $cloud->list($folderId))]);
    }

    #[Route('/api/places/{id}/documents/folders', name: 'api_place_documents_create_folder', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function createFolder(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $name = trim((string) ($body['name'] ?? ''));
        if ('' === $name) {
            throw new HttpException(400, 'Le nom du dossier est requis.');
        }
        $cloud = $this->documentProviders->providerFor($place);
        $rootId = $this->folderOf($cloud, $place);
        $parent = $rootId;
        if ('' !== (string) ($body['folder'] ?? '')) {
            $parent = $this->splitItemId((string) $body['folder'])[1];
            $this->assertInTree($cloud, $rootId, 'folder', $parent);
        }

        return $this->json($this->view($cloud->createFolder($parent, $name)), 201);
    }

    #[Route('/api/places/{id}/documents/upload', name: 'api_place_documents_upload', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function upload(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (null === $file) {
            throw new HttpException(400, 'Aucun fichier envoyé.');
        }
        $cloud = $this->documentProviders->providerFor($place);
        $rootId = $this->folderOf($cloud, $place);
        $folderParam = (string) $request->request->get('folder', '');
        $folderId = $rootId;
        if ('' !== $folderParam) {
            $folderId = $this->splitItemId($folderParam)[1];
            $this->assertInTree($cloud, $rootId, 'folder', $folderId);
        }

        return $this->json($this->view($cloud->upload($folderId, $file)), 201);
    }

    #[Route('/api/places/{id}/documents/{itemId}', name: 'api_place_documents_update', methods: ['PATCH'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function update(#[MapEntity] Place $place, string $itemId, Request $request): JsonResponse
    {
        [$kind, $id] = $this->splitItemId($itemId);
        $cloud = $this->documentProviders->providerFor($place);
        $rootId = $this->folderOf($cloud, $place);
        $this->assertInTree($cloud, $rootId, $kind, $id);
        $body = $request->toArray();
        if (\array_key_exists('name', $body)) {
            $cloud->rename($id, $kind, trim((string) $body['name']));
        }
        if (\array_key_exists('folder', $body)) {
            if ('folder' === $kind && $id === $rootId) {
                throw new HttpException(400, 'Le dossier racine du logement ne peut pas être déplacé.');
            }
            $target = $rootId;
            if (null !== $body['folder']) {
                $target = $this->splitItemId((string) $body['folder'])[1];
                $this->assertInTree($cloud, $rootId, 'folder', $target);
            }
            $cloud->move($id, $kind, $target);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/api/places/{id}/documents/{itemId}', name: 'api_place_documents_delete', methods: ['DELETE'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('PLACE_MANAGE')]
    public function delete(#[MapEntity] Place $place, string $itemId, Request $request): JsonResponse
    {
        [$kind, $id] = $this->splitItemId($itemId);
        $cloud = $this->documentProviders->providerFor($place);
        $rootId = $this->folderOf($cloud, $place);
        $this->assertInTree($cloud, $rootId, $kind, $id);
        if ('folder' === $kind && $id === $rootId) {
            throw new HttpException(400, 'Le dossier racine du logement ne peut pas être supprimé.');
        }
        $folderParam = (string) $request->query->get('folder', '');
        $folderId = $rootId;
        if ('' !== $folderParam) {
            $folderId = $this->splitItemId($folderParam)[1];
            $this->assertInTree($cloud, $rootId, 'folder', $folderId);
        }
        $cloud->remove($folderId, $id, $kind);

        return $this->json(['ok' => true]);
    }

    #[Route('/api/places/{id}/documents/{itemId}/content', name: 'api_place_documents_content', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function content(#[MapEntity] Place $place, string $itemId): Response
    {
        [$kind, $id] = $this->splitItemId($itemId);
        if ('file' !== $kind) {
            throw new HttpException(400, 'Seuls les fichiers ont un contenu.');
        }
        $cloud = $this->documentProviders->providerFor($place);
        // $place kept in the route for the security check (ROLE_USER) and future audit; the id is enough for Rocket Cloud.
        $this->assertInTree($cloud, $this->folderOf($cloud, $place), 'file', $id);

        return new Response($cloud->content($id), 200, ['Content-Type' => 'application/octet-stream']);
    }

    private function folderOf(DocumentProviderInterface $cloud, Place $place): string
    {
        $folderId = $cloud->ensureFolder($place->getId()->toRfc4122(), $place->getCloudFolderId() ?? '', $place->getName());
        if ($folderId !== $place->getCloudFolderId()) {
            $place->setCloudFolderId($folderId);
            $this->em->flush();
        }

        return $folderId;
    }

    /**
     * Every folder/file id received from the client (path, move target, itemId, ...) must resolve inside the
     * place's own Cloud folder tree; otherwise a PMS user could reach another place's documents (or anything
     * else in Rocket Cloud) just by guessing/reusing an id. Not found rather than forbidden, so as not to reveal
     * whether the id exists at all.
     */
    private function assertInTree(DocumentProviderInterface $cloud, string $rootFolderId, string $kind, string $id): void
    {
        if (!$cloud->belongsToPlace($id, $kind, $rootFolderId)) {
            throw new HttpException(404, 'Document introuvable.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function splitItemId(string $itemId): array
    {
        [$kind, $id] = array_pad(explode(':', $itemId, 2), 2, '');
        if (!\in_array($kind, ['folder', 'file'], true) || '' === $id) {
            throw new HttpException(400, 'Identifiant de document invalide.');
        }

        return [$kind, $id];
    }

    /** @param array{id: string, kind: string, name: string, size: ?int, updatedAt: string} $item @return array<string, mixed> */
    private function view(array $item): array
    {
        return [
            'id' => $item['kind'].':'.$item['id'], 'kind' => $item['kind'], 'name' => $item['name'],
            'size' => $item['size'], 'updatedAt' => $item['updatedAt'],
        ];
    }
}
