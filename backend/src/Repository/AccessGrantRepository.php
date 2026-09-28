<?php

namespace App\Repository;

use App\Entity\AccessGrant;
use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccessGrant> */
class AccessGrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessGrant::class);
    }

    /** @return list<AccessGrant> */
    public function forPlace(Place $place): array
    {
        return $this->findBy(['place' => $place], ['validFrom' => 'ASC']);
    }
}
