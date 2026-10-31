<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Mechanic;
use App\Entity\User;
use App\Entity\WorkOrder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Storico degli ordini di lavoro. Persiste soltanto: il flush è del chiamante.
 * Il messaggio è uno dei modelli di ActivityLog::MESSAGES (italiano, coi parametri %nome%):
 * si salva in italiano e si traduce quando si mostra.
 */
class ActivityLogger
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @param array<string, string|int|null> $params valori dei %parametri% del modello */
    public function order(WorkOrder $order, string $message, ?User $user, ?Mechanic $mechanic = null, array $params = []): void
    {
        $text = strtr($message, array_map('strval', $params));
        $this->em->persist(new ActivityLog(ActivityLog::SUBJECT_ORDER, $order->getId(), $text, $user, $mechanic));
    }
}
