<?php

namespace App\Controller;

use App\Entity\Place;
use App\Entity\SmartLock;
use App\Lock\LockProviderRegistry;
use App\Repository\ConnectorRepository;
use App\Repository\PlaceRepository;
use App\Repository\SmartLockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** Locks (live state, last events), their link to a place, and their access grants. */
#[IsGranted('PLACE_READ')]
final class LockController extends AbstractController
{
    public function __construct(
        private readonly LockProviderRegistry $lockProviders,
        private readonly SmartLockRepository $locks,
    ) {
    }

    /** Every lock with the place it opens (Administration: choose the place). */
    #[Route('/api/locks', name: 'api_locks', methods: ['GET'])]
    public function all(): JsonResponse
    {
        $legacy = $this->lockProviders->legacy();

        return $this->json(['demo' => $legacy->isDemo(), 'locks' => $this->withPlace($legacy->listLocks())]);
    }

    /** Registers every lock of the legacy env-token Nuki account not known yet (admin action, replaces the old PMS sync). */
    #[Route('/api/locks/sync', name: 'api_locks_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sync(EntityManagerInterface $em): JsonResponse
    {
        $created = 0;
        foreach ($this->lockProviders->legacy()->listLocks() as $l) {
            $lock = $this->locks->find((int) $l['externalId']);
            if (null === $lock) {
                $em->persist($lock = new SmartLock((int) $l['externalId']));
                ++$created;
            }
            $lock->setName($l['name']);
        }
        $em->flush();

        return $this->json(['created' => $created]);
    }

    /**
     * JSON {"place": "<uuid>"|null, "connector": "<uuid>"|null, "codeConnector": "<uuid>"|null, "externalId": string|null}.
     * `connector`/`codeConnector` reroute this lock's live state / code creation to another connector (its plugin
     * must implement App\Lock\LockCapablePluginInterface); leave null to keep the legacy env-token Nuki API.
     */
    #[Route('/api/locks/{nukiId}', name: 'api_lock_link', methods: ['PUT'], requirements: ['nukiId' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function link(int $nukiId, Request $request, PlaceRepository $places, ConnectorRepository $connectors, EntityManagerInterface $em): JsonResponse
    {
        $lock = $this->locks->find($nukiId) ?? throw new HttpException(404, 'Serrure inconnue : lance d’abord la synchronisation.');
        $body = $request->toArray();
        if (\array_key_exists('place', $body)) {
            $lock->setPlace($this->resolveUuid($body['place'], $places, 'Lieu inconnu.'));
        }
        if (\array_key_exists('connector', $body)) {
            $lock->setConnector($this->resolveUuid($body['connector'], $connectors, 'Connecteur inconnu.'));
        }
        if (\array_key_exists('codeConnector', $body)) {
            $lock->setCodeConnector($this->resolveUuid($body['codeConnector'], $connectors, 'Connecteur inconnu.'));
        }
        if (\array_key_exists('externalId', $body)) {
            $lock->setExternalId(null === $body['externalId'] ? null : (string) $body['externalId']);
        }
        $em->flush();

        return $this->json(['ok' => true]);
    }

    private function resolveUuid(mixed $id, PlaceRepository|ConnectorRepository $repo, string $errorLabel): ?object
    {
        if (null === $id) {
            return null;
        }

        return (Uuid::isValid((string) $id) ? $repo->find(Uuid::fromString((string) $id)) : null) ?? throw new HttpException(422, $errorLabel);
    }

    #[Route('/api/places/{id}/locks', name: 'api_place_locks', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function ofPlace(#[MapEntity] Place $place): JsonResponse
    {
        $legacy = $this->lockProviders->legacy();
        $mine = array_values(array_filter($this->withPlace($legacy->listLocks()), static fn (array $l) => $l['placeId'] === $place->getId()->toRfc4122()));

        // A lock rerouted to another connector for its live state (Home Assistant, Homey...): refresh its entry.
        foreach ($this->locks->findBy(['place' => $place]) as $lock) {
            if (null === $lock->getConnector()) {
                continue;
            }
            $provider = $this->lockProviders->stateProviderFor($lock);
            $found = current(array_filter($provider->listLocks(), static fn (array $l) => $l['externalId'] === $lock->getExternalId()));
            foreach ($mine as $i => $l) {
                if ($l['id'] === $lock->getNukiId()) {
                    $mine[$i] = ($found ?: $l) + ['id' => $lock->getNukiId(), 'provider' => $provider->id(), 'placeId' => $place->getId()->toRfc4122(), 'place' => $place->getName()];
                }
            }
        }

        return $this->json(['demo' => $legacy->isDemo(), 'locks' => $mine]);
    }

    /** @param list<array<string, mixed>> $locks (from a provider: `externalId`, here the numeric Nuki id) @return list<array<string, mixed>> */
    private function withPlace(array $locks): array
    {
        return array_map(function (array $l) {
            $id = (int) $l['externalId'];
            $place = $this->locks->find($id)?->getPlace();
            unset($l['externalId']);

            return ['id' => $id] + $l + ['provider' => 'nuki', 'placeId' => $place?->getId()->toRfc4122(), 'place' => $place?->getName()];
        }, $locks);
    }
}
