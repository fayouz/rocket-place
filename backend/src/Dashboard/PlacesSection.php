<?php

namespace App\Dashboard;

use App\Entity\AccessGrant;
use App\Entity\CleaningTask;
use App\Entity\StockLevel;
use App\Repository\CleaningTaskRepository;
use App\Repository\PlaceRepository;
use App\Repository\SmartLockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Dashboard\DashboardSectionInterface;
use Rocket\Core\Entity\User;

/**
 * The places on the dashboard: counts of places/locks, low/empty stock alerts, cleanings of the day and late ones,
 * upcoming access grants. The list shows today's and late cleanings when there are any, the upcoming access grants otherwise.
 */
final class PlacesSection implements DashboardSectionInterface
{
    public function __construct(
        private readonly PlaceRepository $places,
        private readonly SmartLockRepository $locks,
        private readonly EntityManagerInterface $em,
        private readonly CleaningTaskRepository $cleanings,
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
        $today = new \DateTimeImmutable('today');
        $cleanings = array_values(array_filter(
            $this->cleanings->search($today, $today->modify('+1 day'), null, null, true),
            static fn (CleaningTask $t) => CleaningTask::CANCELLED !== $t->getStatus(),
        ));
        $late = array_values(array_filter($cleanings, static fn (CleaningTask $t) => $t->isLate($now)));
        $cleaningsToday = \count($cleanings) - \count(array_filter($late, static fn (CleaningTask $t) => $t->getScheduledAt() < $today));

        return [
            'kpis' => [
                ['id' => 'places', 'label' => 'Lieux', 'value' => \count($places), 'format' => 'number', 'icon' => 'i-lucide-map-pin', 'tone' => 'bg-primary/10 text-primary'],
                ['id' => 'locks', 'label' => 'Serrures', 'value' => \count($locks), 'format' => 'number', 'icon' => 'i-lucide-lock', 'tone' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400'],
                ['id' => 'stock_alerts', 'label' => 'Stock à réassortir', 'value' => \count($alerts), 'format' => 'number', 'icon' => 'i-lucide-package', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
                ['id' => 'cleanings_today', 'label' => 'Ménages du jour', 'value' => $cleaningsToday, 'format' => 'number', 'icon' => 'i-lucide-sparkles', 'tone' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'],
                ['id' => 'cleanings_late', 'label' => 'Ménages en retard', 'value' => \count($late), 'format' => 'number', 'icon' => 'i-lucide-alarm-clock', 'tone' => 'bg-red-500/10 text-red-600 dark:text-red-400'],
                ['id' => 'access_grants', 'label' => 'Accès à venir', 'value' => \count($upcoming), 'format' => 'number', 'icon' => 'i-lucide-key-round', 'tone' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400'],
            ],
            'series' => [],
            'daily' => [],
            'recent' => [] !== $cleanings ? [
                'title' => 'Ménages du jour et en retard',
                'link' => '/menage',
                'empty' => 'Aucun ménage aujourd’hui.',
                'items' => array_map(static fn (CleaningTask $t) => [
                    'id' => $t->getId()->toRfc4122(),
                    'title' => $t->getPlace()->getName(),
                    'subtitle' => $t->getLabel().(null !== $t->getAssignee() ? ' · '.$t->getAssignee()->getDisplayName() : ' · non attribué'),
                    'at' => $t->getScheduledAt()->format(\DATE_ATOM),
                    'badge' => $t->isLate($now) ? 'En retard' : match ($t->getStatus()) { CleaningTask::DONE => 'Fait', CleaningTask::IN_PROGRESS => 'En cours', default => 'À faire' },
                    'badgeColor' => $t->isLate($now) ? 'error' : match ($t->getStatus()) { CleaningTask::DONE => 'success', CleaningTask::IN_PROGRESS => 'info', default => 'neutral' },
                    'link' => '/menage',
                ], \array_slice($cleanings, 0, 8)),
            ] : [
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
                ['label' => 'Ménage', 'icon' => 'i-lucide-sparkles', 'to' => '/menage', 'tone' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'],
                ['label' => 'Stock', 'icon' => 'i-lucide-package', 'to' => '/stock', 'tone' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            ],
        ];
    }
}
