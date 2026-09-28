<?php

namespace App\Security;

use Rocket\Core\Security\ApplicationUser;
use Rocket\Core\Security\ScopeGuardListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * rocket-core only lets an application that does not impersonate anyone call GET /api/me. Place opens its own
 * business endpoints (places, locks, access grants, domotique, documents, stock, cleanings) to such applications, so a PMS can
 * drive Place server-to-server; every other endpoint (users, applications, connectors and their secrets, settings…)
 * stays guarded by rocket-core's listener.
 */
#[AsDecorator(ScopeGuardListener::class)]
final class PlaceScopeGuardListener
{
    private const APPLICATION_PATTERN = '#^/api/(places(/[^/]+(/(access-grants|locks|domotique|documents|cleanings|cleaning-checklist)(/.*)?)?)?|access-grants/[^/]+/(send|revoke)|cleanings(/[^/]+(/(photos|stock|link))?)?|locks|stock-items(/[^/]+)?|stock-levels(/[^/]+)?)$#';

    public function __construct(
        #[AutowireDecorated] private readonly ScopeGuardListener $inner,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->security->getUser() instanceof ApplicationUser
            && preg_match(self::APPLICATION_PATTERN, $event->getRequest()->getPathInfo())) {
            return;
        }

        ($this->inner)($event);
    }
}
