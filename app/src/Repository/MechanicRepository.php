<?php

namespace App\Repository;

use App\Entity\Mechanic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mechanic>
 */
class MechanicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mechanic::class);
    }

    /** @return Mechanic[] */
    public function findActive(): array
    {
        return $this->findBy(['cancel' => 0], ['sort' => 'ASC', 'name' => 'ASC']);
    }
}
