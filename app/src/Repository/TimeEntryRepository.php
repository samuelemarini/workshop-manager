<?php

namespace App\Repository;

use App\Entity\Mechanic;
use App\Entity\TimeEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntry>
 */
class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    /** La registrazione aperta del meccanico (al massimo una) */
    public function findOpen(Mechanic $mechanic): ?TimeEntry
    {
        return $this->findOneBy(['mechanic' => $mechanic, 'endedAt' => null]);
    }

    /** @return TimeEntry[] registrazioni che toccano il periodo [from, to) */
    public function findBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('m')
            ->join('e.mechanic', 'm')
            ->where('e.startedAt < :to AND (e.endedAt IS NULL OR e.endedAt > :from)')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getResult();
    }

    /**
     * Secondi lavorati per meccanico nel periodo (le registrazioni a cavallo contano solo per la parte dentro).
     *
     * @return array<int, int> id meccanico => secondi
     */
    public function secondsByMechanic(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $now = new \DateTimeImmutable();
        $totals = [];
        foreach ($this->findBetween($from, $to) as $e) {
            $start = max($e->getStartedAt()->getTimestamp(), $from->getTimestamp());
            $end = min(($e->getEndedAt() ?? $now)->getTimestamp(), $to->getTimestamp());
            $id = $e->getMechanic()->getId();
            $totals[$id] = ($totals[$id] ?? 0) + max(0, $end - $start);
        }

        return $totals;
    }
}
