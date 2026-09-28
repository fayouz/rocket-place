<?php

namespace App\Code;

use App\Entity\AccessGrant;
use App\Entity\Place;
use App\Entity\SmartLock;
use App\Lock\LockProviderRegistry;
use App\Repository\AccessGrantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Access grants: planned in the database on an explicit call (::plan), a unique keypad code is reserved right away
 * so two grants never collide on the same lock, then written to the physical lock only on a further explicit call
 * (::send). ::revoke marks the grant revoked and best-effort removes the code from the lock.
 */
final class AccessGrantService
{
    public function __construct(
        private readonly LockProviderRegistry $lockProviders,
        private readonly AccessGrantRepository $grants,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function plan(Place $place, SmartLock $lock, string $label, \DateTimeImmutable $from, \DateTimeImmutable $until, ?string $externalRef = null): AccessGrant
    {
        if ($until <= $from) {
            throw new HttpException(422, $this->translator->trans('access_grant.invalid_dates'));
        }
        $grant = new AccessGrant($place, $lock, $label, $this->newCode($lock), $from, $until, $externalRef);
        $this->em->persist($grant);
        $this->em->flush();

        return $grant;
    }

    /** Writes the code to the lock (explicit user action). */
    public function send(AccessGrant $grant): AccessGrant
    {
        if (AccessGrant::CREATED === $grant->getStatus()) {
            throw new HttpException(409, $this->translator->trans('access_grant.already_created'));
        }
        if (AccessGrant::REVOKED === $grant->getStatus()) {
            throw new HttpException(409, $this->translator->trans('access_grant.revoked'));
        }
        try {
            $this->lockProviders->codeProviderFor($grant->getLock())->createCode($grant->getLock(), $grant->getCode(), $grant->getLabel(), $grant->getValidFrom(), $grant->getValidUntil());
        } catch (HttpException $e) {
            $grant->markError($e->getMessage());
            $this->em->flush();
            throw $e;
        }
        $grant->markCreated($this->clock->now());
        $this->em->flush();

        return $grant;
    }

    /** Revokes the grant; best-effort removal of the code from the lock (not every provider supports it). */
    public function revoke(AccessGrant $grant): AccessGrant
    {
        if (AccessGrant::CREATED === $grant->getStatus()) {
            try {
                $this->lockProviders->codeProviderFor($grant->getLock())->deleteCode($grant->getLock(), $grant->getCode());
            } catch (HttpException) {
                // best-effort: the code may need to be removed by hand in the provider's own console
            }
        }
        $grant->markRevoked($this->clock->now());
        $this->em->flush();

        return $grant;
    }

    /** 6 digits without 0, not starting with 12 (Nuki keypad rules), unique on the lock. */
    private function newCode(SmartLock $lock): string
    {
        $taken = array_map(static fn (AccessGrant $g) => $g->getCode(), $this->grants->findBy(['lock' => $lock]));
        do {
            $code = '';
            for ($i = 0; $i < 6; ++$i) {
                $code .= (string) random_int(1, 9);
            }
        } while (str_starts_with($code, '12') || \in_array($code, $taken, true));

        return $code;
    }
}
