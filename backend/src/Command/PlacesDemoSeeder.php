<?php

namespace App\Command;

use App\Code\AccessGrantService;
use App\Entity\AccessGrant;
use App\Entity\CleaningChecklistItem;
use App\Entity\CleaningTask;
use App\Entity\Connector;
use App\Entity\Place;
use App\Entity\SmartLock;
use App\Entity\StockItem;
use App\Entity\StockLevel;
use App\Repository\PlaceRepository;
use App\Repository\SmartLockRepository;
use App\Repository\StockItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Rocket\Core\Command\DemoSeederInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/** Demo data: two places, their locks, a demo Homey connector, a stock catalogue and one planned access grant,
 * a cleaning checklist per place and cleanings (one today, one late). Idempotent. */
final class PlacesDemoSeeder implements DemoSeederInterface
{
    /** Fixed ids of the demo places, shared by the demo seeders of Clean, Stock, Linen, PMS and Cast. */
    public const PORT = '0192f7c4-0000-7000-8000-000000000001';
    public const VIGNES = '0192f7c4-0000-7000-8000-000000000002';

    public function __construct(
        private readonly PlaceRepository $places,
        private readonly SmartLockRepository $locks,
        private readonly StockItemRepository $stockItems,
        private readonly AccessGrantService $accessGrants,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function seed(array $users, SymfonyStyle $io): void
    {
        $port = $this->demoPlace(self::PORT, 'Le port');
        if (null === $port) {
            $port = (new Place(Uuid::fromString(self::PORT)))->setName('Le port')->setAddress('12 quai des Pêcheurs, 17000 La Rochelle')->setColor('blue')->setCoordinates(46.1591, -1.1520);
            $this->em->persist($port);
            $this->em->persist((new Connector($port, 'homey'))->setName('Homey (démo)'));
        }
        $vignes = $this->demoPlace(self::VIGNES, 'Les vignes');
        if (null === $vignes) {
            $vignes = (new Place(Uuid::fromString(self::VIGNES)))->setName('Les vignes')->setAddress('4 chemin des Vignes, 33000 Bordeaux')->setColor('green')->setCoordinates(44.8378, -0.5792);
            $this->em->persist($vignes);
        }
        $this->em->flush();

        $lock1 = $this->locks->find(90001);
        if (null === $lock1) {
            $this->em->persist($lock1 = new SmartLock(90001));
        }
        $lock1->setName('Port - entrée')->setPlace($port);
        $lock2 = $this->locks->find(90002);
        if (null === $lock2) {
            $this->em->persist($lock2 = new SmartLock(90002));
        }
        $lock2->setName('Vignes - entrée')->setPlace($vignes);
        $this->em->flush();

        $catalogue = [
            ['name' => 'Papier toilette', 'asin' => 'B07PGL7C4L', 'reorderQty' => 12, 'subscription' => false],
            ['name' => 'Liquide vaisselle', 'asin' => 'B08J8L9F7T', 'reorderQty' => 2, 'subscription' => false],
            ['name' => 'Café dosettes', 'asin' => 'B01N7Y6ZKX', 'reorderQty' => 4, 'subscription' => true],
            ['name' => 'Sacs poubelle', 'asin' => 'B003AXFHW0', 'reorderQty' => 3, 'subscription' => false],
        ];
        $items = [];
        foreach ($catalogue as $c) {
            $item = $this->stockItems->findOneBy(['name' => $c['name']]);
            if (null === $item) {
                $item = (new StockItem())->setName($c['name'])->setAsin($c['asin'])->setReorderQty($c['reorderQty'])->setSubscription($c['subscription']);
                $this->em->persist($item);
            }
            $items[] = $item;
        }
        $this->em->flush();

        foreach ([$port, $vignes] as $place) {
            foreach ($items as $i => $item) {
                $existing = $this->em->getRepository(StockLevel::class)->findOneBy(['place' => $place, 'item' => $item]);
                if (null === $existing) {
                    $level = $place === $port && 0 === $i ? 'low' : 'ok';
                    $this->em->persist(new StockLevel($place, $item, $level));
                }
            }
        }
        $this->em->flush();

        $existingGrant = $this->em->getRepository(AccessGrant::class)->findOneBy(['place' => $port, 'externalRef' => 'demo-1']);
        if (null === $existingGrant) {
            $from = new \DateTimeImmutable('+2 days 15:00');
            $until = new \DateTimeImmutable('+5 days 11:00');
            $this->accessGrants->plan($port, $lock1, 'Sofia Rossi', $from, $until, 'demo-1');
        }

        $checklists = [
            [$port, ['Draps et serviettes changés', 'Salle de bain et WC', 'Cuisine et vaisselle', 'Poubelles descendues', 'Machine à café détartrée']],
            [$vignes, ['Draps et serviettes changés', 'Salle de bain et WC', 'Terrasse balayée', 'Poubelles descendues']],
        ];
        foreach ($checklists as [$place, $labels]) {
            if ([] === $this->em->getRepository(CleaningChecklistItem::class)->findBy(['place' => $place])) {
                foreach ($labels as $i => $label) {
                    $this->em->persist(new CleaningChecklistItem($place, $label, $i));
                }
            }
        }
        $cleaner = null; // the first demo user who is not an administrator, else anyone
        foreach ($users as $user) {
            if (!\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $cleaner = $user;
                break;
            }
        }
        $cleaner ??= [] === $users ? null : reset($users);
        $cleanings = [
            [$port, 'demo-menage-1', 'Ménage après Sofia Rossi', new \DateTimeImmutable('today 11:00'), new \DateTimeImmutable('today 16:00'), $checklists[0][1]],
            [$vignes, 'demo-menage-2', 'Ménage de fin de séjour', new \DateTimeImmutable('-1 day 11:00'), new \DateTimeImmutable('-1 day 16:00'), $checklists[1][1]],
        ];
        foreach ($cleanings as [$place, $ref, $label, $at, $due, $labels]) {
            if (null === $this->em->getRepository(CleaningTask::class)->findOneBy(['place' => $place, 'externalRef' => $ref])) {
                $this->em->persist((new CleaningTask($place, $label, $at, $labels, $ref))->setDueAt($due)->setAssignee($cleaner));
            }
        }
        $this->em->flush();

        $io->text('Rocket Place : 2 lieux, 2 serrures, 1 connecteur, '.\count($items).' articles de stock, 1 autorisation d’accès planifiée, 2 ménages.');
    }

    /**
     * The demo place with the fixed $id; a demo place of the same name seeded earlier with a random id (before the ids
     * were fixed) is re-keyed to $id, every reference to it included, so the other bricks' demo data line up.
     */
    private function demoPlace(string $id, string $name): ?Place
    {
        $place = $this->places->find(Uuid::fromString($id));
        if (null !== $place) {
            return $place;
        }
        $legacy = $this->places->findOneBy(['name' => $name]);
        if (null === $legacy) {
            return null;
        }
        $old = $legacy->getId()->toRfc4122();
        $conn = $this->em->getConnection();
        $meta = $this->em->getClassMetadata(Place::class);
        $columns = array_values(array_diff(array_map(fn (string $f) => $meta->getColumnName($f), $meta->getFieldNames()), ['id']));
        $this->em->detach($legacy);
        $conn->transactional(function () use ($conn, $meta, $columns, $id, $old): void {
            $list = implode(', ', $columns);
            $conn->executeStatement(\sprintf('INSERT INTO %1$s (id, %2$s) SELECT ?, %2$s FROM %1$s WHERE id = ?', $meta->getTableName(), $list), [$id, $old]);
            foreach ($this->em->getMetadataFactory()->getAllMetadata() as $m) {
                foreach ($m->getAssociationMappings() as $assoc) {
                    if (Place::class !== $assoc['targetEntity'] || !$assoc['isOwningSide'] || !isset($assoc['joinColumns'])) {
                        continue;
                    }
                    foreach ($assoc['joinColumns'] as $jc) {
                        $conn->executeStatement(\sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $m->getTableName(), $jc['name'], $jc['name']), [$id, $old]);
                    }
                }
            }
            $conn->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', $meta->getTableName()), [$old]);
        });

        return $this->places->find(Uuid::fromString($id));
    }
}
