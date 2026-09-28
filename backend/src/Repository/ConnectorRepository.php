<?php

namespace App\Repository;

use App\Entity\Connector;
use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Connector> */
class ConnectorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Connector::class);
    }

    /** @return list<Connector> */
    public function forPlace(Place $place): array
    {
        return $this->findBy(['place' => $place], ['name' => 'ASC']);
    }
}
