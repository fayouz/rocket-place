<?php

namespace App\Command;

use App\Code\AccessGrantService;
use App\Entity\AccessGrant;
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

/** Demo data: two places, their locks, a demo Homey connector, a stock catalogue and one planned access grant. Idempotent. */
final class PlacesDemoSeeder implements DemoSeederInterface
{
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
        $port = $this->places->findOneBy(['name' => 'Le port']);
        if (null === $port) {
            $port = (new Place())->setName('Le port')->setAddress('12 quai des Pêcheurs, 17000 La Rochelle')->setColor('blue')->setCoordinates(46.1591, -1.1520);
            $this->em->persist($port);
            $this->em->persist((new Connector($port, 'homey'))->setName('Homey (démo)'));
        }
        $vignes = $this->places->findOneBy(['name' => 'Les vignes']);
        if (null === $vignes) {
            $vignes = (new Place())->setName('Les vignes')->setAddress('4 chemin des Vignes, 33000 Bordeaux')->setColor('green')->setCoordinates(44.8378, -0.5792);
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

        $io->text('Rocket Place : 2 lieux, 2 serrures, 1 connecteur, '.\count($items).' articles de stock, 1 autorisation d’accès planifiée.');
    }
}
