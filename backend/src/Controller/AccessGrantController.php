<?php

namespace App\Controller;

use App\Code\AccessGrantService;
use App\Entity\AccessGrant;
use App\Entity\Place;
use App\Repository\AccessGrantRepository;
use App\Repository\SmartLockRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Access grants (temporary keypad codes) of a place: list/plan/send/revoke. Send never happens implicitly. */
#[IsGranted('ROLE_USER')]
final class AccessGrantController extends AbstractController
{
    public function __construct(
        private readonly AccessGrantService $grants,
        private readonly AccessGrantRepository $repository,
        private readonly SmartLockRepository $locks,
    ) {
    }

    #[Route('/api/places/{id}/access-grants', name: 'api_place_access_grants', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
    public function list(#[MapEntity] Place $place): JsonResponse
    {
        return $this->json(array_map(static fn (AccessGrant $g) => $g->toArray(), $this->repository->forPlace($place)));
    }

    /** JSON {"lockId": int, "label": string, "validFrom": ISO-8601, "validUntil": ISO-8601, "externalRef"?: string|null}. Only plans the grant. */
    #[Route('/api/places/{id}/access-grants', name: 'api_place_access_grants_plan', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('ROLE_ADMIN')]
    public function plan(#[MapEntity] Place $place, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $lock = $this->locks->find((int) ($body['lockId'] ?? 0)) ?? throw new HttpException(422, 'Serrure inconnue.');
        $label = trim((string) ($body['label'] ?? ''));
        if ('' === $label || mb_strlen($label) > 120) {
            throw new HttpException(422, 'Libellé requis (120 caractères au plus).');
        }
        try {
            $from = new \DateTimeImmutable((string) ($body['validFrom'] ?? ''));
            $until = new \DateTimeImmutable((string) ($body['validUntil'] ?? ''));
        } catch (\Exception) {
            throw new HttpException(422, 'Dates invalides.');
        }
        $externalRef = null === ($body['externalRef'] ?? null) ? null : mb_substr((string) $body['externalRef'], 0, 120);
        $grant = $this->grants->plan($place, $lock, $label, $from, $until, $externalRef);

        return $this->json($grant->toArray(), 201);
    }

    /** Writes the code to the physical lock (explicit action, never called from tests). */
    #[Route('/api/access-grants/{id}/send', name: 'api_access_grant_send', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('ROLE_ADMIN')]
    public function send(#[MapEntity] AccessGrant $grant): JsonResponse
    {
        return $this->json($this->grants->send($grant)->toArray());
    }

    #[Route('/api/access-grants/{id}/revoke', name: 'api_access_grant_revoke', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted('ROLE_ADMIN')]
    public function revoke(#[MapEntity] AccessGrant $grant): JsonResponse
    {
        return $this->json($this->grants->revoke($grant)->toArray());
    }
}
