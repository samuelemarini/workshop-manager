<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Mechanic;
use App\Entity\User;
use App\Entity\WorkOrder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Storico degli ordini di lavoro. Persiste soltanto: il flush è del chiamante.
 */
class ActivityLogger
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function order(WorkOrder $order, string $message, ?User $user, ?Mechanic $mechanic = null): void
    {
        $this->em->persist(new ActivityLog(ActivityLog::SUBJECT_ORDER, $order->getId(), $message, $user, $mechanic));
    }
}
