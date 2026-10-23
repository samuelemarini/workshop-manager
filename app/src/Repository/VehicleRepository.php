<?php

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    public function findOneByPlate(?string $plate): ?Vehicle
    {
        $plate = Vehicle::normalizePlate($plate);

        return $plate === '' ? null : $this->findOneBy(['plate' => $plate]);
    }

    /** @return Vehicle[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.owner', 'o')->addSelect('o')
            ->where('v.cancel = 0')
            ->orderBy('v.plate', 'ASC')
            ->getQuery()->getResult();
    }
}
