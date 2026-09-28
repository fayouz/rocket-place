<?php

namespace App\Dashboard;

use App\Entity\AccessGrant;
use App\Entity\StockLevel;
use App\Repository\PlaceRepository;
use App\Repository\SmartLockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Dashboard\DashboardSectionInterface;
use Rocket\Core\Entity\User;

/**
 * The places on the dashboard: counts of places/locks, low/empty stock alerts, upcoming access grants (listed).
 * Cleanings live in Rocket Clean.
 */
final class PlacesSection implements DashboardSectionInterface
{
    public function __construct(
        private readonly PlaceRepository $places,
        private readonly SmartLockRepository $locks,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function build(User $user, bool $admin, \DateTimeImmutable $from, \DateTimeImmutable $previousFrom): array
    {
        $places = $this->places->findAll();
        $locks = $this->locks->findAll();
        $alerts = $this->em->getRepository(StockLevel::class)->createQueryBuilder('l')->where('l.level != :ok')->setParameter('ok', 'ok')->getQuery()->getResult();
        $now = new \DateTimeImmutable();
        $upcoming = $this->em->getRepository(AccessGrant::class)->createQueryBuilder('g')
            ->where('g.status = :planned OR g.status = :created')
            ->andWhere('g.validUntil >= :now')
            ->setParameter('planned', AccessGrant::PLANNED)->setParameter('created', AccessGrant::CREATED)->setParameter('now', $now)
            ->orderBy('g.validFrom', 'ASC')->setMaxResults(6)->getQuery()->getResult();

        return [
            'kpis' => [
                ['id' => 'places', 'label' => 'Lieux', 'value' => \count($places), 'format' => 'number', 'icon' => 'i-lucide-map-pin', 'tone' => 'bg-primary/10 text-primary'],
                ['id' => 'locks', 'label' => 'Serrures', 'value' => \count($locks), 'format' => 'number', 'icon' => 'i-lucide-lock', 'tone' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400'],
                ['id' => 'stock_alerts', 'label' => 'Stock à réassortir', 'value' => \count($alerts), 'format' => 'number', 'icon' => 'i-lucide-package', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
                ['id' => 'access_grants', 'label' => 'Accès à venir', 'value' => \count($upcoming), 'format' => 'number', 'icon' => 'i-lucide-key-round', 'tone' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400'],
            ],
            'series' => [],
            'daily' => [],
            'recent' => [
                'title' => 'Prochains accès',
                'link' => '/places',
                'empty' => 'Aucun accès planifié.',
                'items' => array_map(static fn (AccessGrant $g) => [
                    'id' => $g->getId()->toRfc4122(),
                    'title' => $g->getLabel(),
                    'subtitle' => $g->getPlace()->getName(),
                    'at' => $g->getValidFrom()->format(\DATE_ATOM),
                    'badge' => $g->getValidFrom()->format('d/m'),
                    'badgeColor' => 'neutral',
                    'link' => '/places/'.$g->getPlace()->getId()->toRfc4122(),
                ], $upcoming),
            ],
            'quickActions' => [
                ['label' => 'Lieux', 'icon' => 'i-lucide-map-pin', 'to' => '/places', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => 'Stock', 'icon' => 'i-lucide-package', 'to' => '/stock', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            ],
        ];
    }
}
