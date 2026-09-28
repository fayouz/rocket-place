<?php

namespace App\Repository;

use App\Entity\Place;
use App\Entity\StockLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StockLevel> */
class StockLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockLevel::class);
    }

    /** @return list<StockLevel> */
    public function forPlace(Place $place): array
    {
        return $this->findBy(['place' => $place]);
    }
}
