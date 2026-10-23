<?php

namespace App\Repository;

use App\Entity\WorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkOrder>
 */
class WorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrder::class);
    }

    public function nextNumber(int $year): int
    {
        return 1 + (int) $this->createQueryBuilder('o')
            ->select('MAX(o.number)')
            ->where('o.year = :y')->setParameter('y', $year)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Ordini in officina (non consegnati né annullati) più quelli consegnati da $deliveredSince,
     * con lavorazioni, meccanici e tempi già caricati.
     *
     * @return WorkOrder[]
     */
    public function findBoard(?\DateTimeInterface $deliveredSince = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('c', 'v', 't', 'm', 'e')
            ->join('o.customer', 'c')
            ->join('o.vehicle', 'v')
            ->leftJoin('o.tasks', 't')
            ->leftJoin('t.mechanic', 'm')
            ->leftJoin('t.timeEntries', 'e')
            ->where('o.status NOT IN (:closed)')
            ->setParameter('closed', [WorkOrder::DELIVERED, WorkOrder::CANCELLED])
            ->orderBy('o.promisedAt', 'ASC')
            ->addOrderBy('o.number', 'ASC');
        if ($deliveredSince) {
            $qb->orWhere('o.status = :delivered AND o.deliveredAt >= :since')
                ->setParameter('delivered', WorkOrder::DELIVERED)
                ->setParameter('since', $deliveredSince);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return WorkOrder[] consegne promesse nel periodo [from, to), annullati esclusi */
    public function findPromisedBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('c', 'v')
            ->join('o.customer', 'c')
            ->join('o.vehicle', 'v')
            ->where('o.promisedAt >= :from AND o.promisedAt < :to')
            ->andWhere('o.status != :cancelled')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('cancelled', WorkOrder::CANCELLED)
            ->orderBy('o.promisedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return WorkOrder[] tutti gli ordini, i più recenti prima (elenco con ricerca) */
    public function findAllForList(): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('c', 'v')
            ->join('o.customer', 'c')
            ->join('o.vehicle', 'v')
            ->orderBy('o.acceptedAt', 'DESC')
            ->getQuery()->getResult();
    }
}
