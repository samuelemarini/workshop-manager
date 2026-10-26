<?php

namespace App\Repository;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Entity\WorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    private function withOrder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->addSelect('o', 'v', 'c', 'm', 'e')
            ->join('t.workOrder', 'o')
            ->join('o.vehicle', 'v')
            ->join('o.customer', 'c')
            ->leftJoin('t.mechanic', 'm')
            ->leftJoin('t.timeEntries', 'e');
    }

    /** @return Task[] lavorazioni da mostrare sul planning di un giorno */
    public function findForDay(\DateTimeInterface $day): array
    {
        $from = \DateTimeImmutable::createFromInterface($day)->setTime(0, 0);

        return $this->findInRange($from, $from->modify('+1 day'));
    }

    /**
     * Lavorazioni pianificate nel periodo [from, to) oppure lavorate nel periodo
     * (registrazioni tempi), anche se non pianificate. Le rifiutate non compaiono.
     *
     * @return Task[]
     */
    public function findInRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $ids = $this->getEntityManager()->createQuery(
            'SELECT DISTINCT IDENTITY(e.task) FROM App\Entity\TimeEntry e WHERE e.startedAt < :to AND (e.endedAt IS NULL OR e.endedAt >= :from)'
        )->setParameters(['from' => $from, 'to' => $to])->getSingleColumnResult();

        $qb = $this->withOrder()
            ->where('t.plannedStart >= :from AND t.plannedStart < :to')
            ->setParameter('from', $from)->setParameter('to', $to);
        if ($ids) {
            $qb->orWhere('t.id IN (:ids)')->setParameter('ids', $ids);
        }

        return $qb->andWhere('t.status NOT IN (:hidden)')
            ->setParameter('hidden', [Task::REJECTED])
            ->orderBy('t.plannedStart', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Lavorazioni ancora da iniziare senza un orario: la colonna "Da pianificare".
     * Quelle già lavorate (in pausa, completate o riaperte) non compaiono.
     *
     * @return Task[]
     */
    public function findUnscheduled(): array
    {
        return $this->withOrder()
            ->where('t.plannedStart IS NULL')
            ->andWhere('t.status = :todo')
            ->andWhere('t.timeEntries IS EMPTY')
            ->andWhere('o.status NOT IN (:closed)')
            ->setParameter('todo', Task::TODO)
            ->setParameter('closed', [WorkOrder::READY, WorkOrder::DELIVERED, WorkOrder::CANCELLED])
            ->orderBy('o.promisedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Le lavorazioni di un meccanico che il tablet mostra oggi: quelle in corso o in pausa,
     * quelle da fare pianificate fino a oggi o senza orario, quelle completate oggi.
     *
     * @return Task[]
     */
    public function findForMechanic(Mechanic $mechanic, \DateTimeInterface $day): array
    {
        $end = \DateTimeImmutable::createFromInterface($day)->setTime(23, 59, 59);
        $start = $end->setTime(0, 0);

        return $this->withOrder()
            ->where('t.mechanic = :m')
            ->andWhere('o.status NOT IN (:closed)')
            ->andWhere('t.status IN (:active) OR (t.status = :todo AND (t.plannedStart IS NULL OR t.plannedStart <= :end)) OR (t.status = :done AND t.completedAt >= :start)')
            ->setParameter('m', $mechanic)
            ->setParameter('closed', [WorkOrder::CANCELLED])
            ->setParameter('active', [Task::RUNNING, Task::PAUSED])
            ->setParameter('todo', Task::TODO)
            ->setParameter('done', Task::DONE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.plannedStart', 'ASC')
            ->addOrderBy('o.promisedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return Task[] lavorazioni da fare senza meccanico: sul tablet "lavori liberi da prendere" */
    public function findFree(): array
    {
        return $this->withOrder()
            ->where('t.mechanic IS NULL')
            ->andWhere('t.status = :todo')
            ->andWhere('o.status NOT IN (:closed)')
            ->setParameter('todo', Task::TODO)
            ->setParameter('closed', [WorkOrder::DELIVERED, WorkOrder::CANCELLED])
            ->orderBy('o.promisedAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return Task[] lavorazioni completate nel periodo (confronto stimato / effettivo) */
    public function findCompletedBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->withOrder()
            ->where('t.status = :done AND t.completedAt >= :from AND t.completedAt < :to')
            ->setParameter('done', Task::DONE)
            ->setParameter('from', $from)->setParameter('to', $to)
            ->orderBy('t.completedAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return Task[] segnalazioni dal tablet in attesa dell'OK del cliente */
    public function findProposed(): array
    {
        return $this->withOrder()
            ->where('t.status = :p')->setParameter('p', Task::PROPOSED)
            ->orderBy('t.proposedAt', 'ASC')
            ->getQuery()->getResult();
    }
}
