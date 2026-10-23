<?php

namespace App\Service;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WorkOrder;
use App\Repository\TimeEntryRepository;

/**
 * Regole di avanzamento di lavorazioni e ordini, usate dal tablet e dall'ufficio.
 *
 * - un meccanico lavora su una cosa alla volta: avviarne un'altra mette in pausa quella in corso;
 * - la prima lavorazione avviata porta l'ordine "In lavorazione";
 * - una pausa per ricambi o OK del cliente, senza altre lavorazioni in corso, porta l'ordine "In attesa";
 * - quando tutte le lavorazioni sono completate l'ordine diventa "Pronta", oppure "In attesa"
 *   se ci sono lavori extra che aspettano ancora l'OK del cliente.
 *
 * Persiste e scrive lo storico, il flush è del chiamante.
 */
class Workflow
{
    private const WAITING_OK = 'Attesa OK del cliente';

    public function __construct(
        private TimeEntryRepository $entries,
        private ActivityLogger $logger,
    ) {
    }

    public function start(Task $task, Mechanic $mechanic, ?User $user): void
    {
        if (!in_array($task->getStatus(), [Task::TODO, Task::PAUSED, Task::RUNNING], true)) {
            throw new \DomainException('Questa lavorazione non si può avviare ('.mb_strtolower($task->getStatusLabel()).').');
        }
        $open = $task->getOpenEntry();
        if ($open && $open->getMechanic() !== $mechanic) {
            throw new \DomainException('Ci sta già lavorando '.$open->getMechanic()->getName().'.');
        }
        if ($open) {
            return;
        }

        // una cosa alla volta: quello che stava facendo va in pausa
        $current = $this->entries->findOpen($mechanic);
        if ($current) {
            $this->pause($current->getTask(), 'Passato a un altro lavoro', $user, $mechanic);
        }

        $task->getMechanic() ?? $task->setMechanic($mechanic);
        $task->addTimeEntry(new TimeEntry($mechanic));
        $wasPaused = $task->getStatus() === Task::PAUSED;
        $task->setStatus(Task::RUNNING)->setPauseReason(null);

        $order = $task->getWorkOrder();
        if (in_array($order->getStatus(), [WorkOrder::ACCEPTED, WorkOrder::WAITING, WorkOrder::READY], true)) {
            $order->setStatus(WorkOrder::IN_PROGRESS)->setWaitingReason(null)->setReadyAt(null);
        }
        $this->logger->order($order, ($wasPaused ? 'Ripresa: ' : 'Avviata: ').$task->getTitle(), $user, $mechanic);
    }

    public function pause(Task $task, string $reason, ?User $user, ?Mechanic $mechanic = null): void
    {
        $open = $task->getOpenEntry();
        $open?->close($reason);
        if ($task->getStatus() !== Task::RUNNING) {
            return;
        }
        $task->setStatus(Task::PAUSED)->setPauseReason($reason);
        $this->logger->order($task->getWorkOrder(), 'In pausa ('.$reason.'): '.$task->getTitle(), $user, $mechanic ?? $open?->getMechanic());

        $order = $task->getWorkOrder();
        if (in_array($reason, Task::WAITING_REASONS, true) && !$order->getRunningTask()) {
            $order->setStatus(WorkOrder::WAITING)->setWaitingReason($reason.' · '.$task->getTitle());
        }
    }

    public function finish(Task $task, ?User $user, ?Mechanic $mechanic = null): void
    {
        $open = $task->getOpenEntry();
        $open?->close('Completata');
        $task->setStatus(Task::DONE)->setPauseReason(null)->setCompletedAt(new \DateTime());
        $this->logger->order($task->getWorkOrder(), 'Completata: '.$task->getTitle(), $user, $mechanic ?? $open?->getMechanic());
        $this->syncOrder($task->getWorkOrder(), $user);
    }

    /** Riapre una lavorazione chiusa per errore (ufficio) */
    public function reopen(Task $task, ?User $user): void
    {
        $task->setStatus(Task::TODO)->setCompletedAt(null);
        $order = $task->getWorkOrder();
        if ($order->getStatus() === WorkOrder::READY) {
            $order->setStatus($order->getWorkedSeconds() > 0 ? WorkOrder::IN_PROGRESS : WorkOrder::ACCEPTED)->setReadyAt(null);
        }
        $this->logger->order($order, 'Riaperta: '.$task->getTitle(), $user);
    }

    /** Segnalazione dal tablet: lavoro extra che aspetta l'OK del cliente */
    public function propose(WorkOrder $order, Task $task, Mechanic $mechanic, ?User $user): void
    {
        $task->setStatus(Task::PROPOSED)->setProposedBy($mechanic)->setProposedAt(new \DateTime())->setMechanic($mechanic);
        $order->addTask($task);
        $this->logger->order($order, ($task->isUrgent() ? 'Problema di sicurezza segnalato: ' : 'Lavoro extra proposto: ').$task->getTitle(), $user, $mechanic);
    }

    public function decide(Task $task, bool $approved, ?User $user): void
    {
        if ($task->getStatus() !== Task::PROPOSED) {
            return;
        }
        $task->setStatus($approved ? Task::TODO : Task::REJECTED);
        $order = $task->getWorkOrder();
        $this->logger->order($order, ($approved ? 'OK del cliente: ' : 'Rifiutata dal cliente: ').$task->getTitle(), $user);

        $waitingForOk = $order->getStatus() === WorkOrder::WAITING && str_starts_with((string) $order->getWaitingReason(), self::WAITING_OK);
        if (($approved && $order->getStatus() === WorkOrder::READY) || ($waitingForOk && !$order->getProposedTasks())) {
            $order->setStatus(WorkOrder::IN_PROGRESS)->setWaitingReason(null)->setReadyAt(null);
        }
        $this->syncOrder($order, $user);
    }

    /** Tutte le lavorazioni completate: l'auto è pronta (o aspetta l'OK del cliente sugli extra) */
    public function syncOrder(WorkOrder $order, ?User $user): void
    {
        $active = $order->getActiveTasks();
        if (!$active || !in_array($order->getStatus(), [WorkOrder::ACCEPTED, WorkOrder::IN_PROGRESS, WorkOrder::WAITING], true)) {
            return;
        }
        foreach ($active as $task) {
            if ($task->getStatus() !== Task::DONE) {
                return;
            }
        }
        if ($proposed = $order->getProposedTasks()) {
            if ($order->getStatus() !== WorkOrder::WAITING) {
                $order->setStatus(WorkOrder::WAITING)->setWaitingReason(self::WAITING_OK.' · '.count($proposed).(count($proposed) === 1 ? ' lavoro extra' : ' lavori extra'));
                $this->logger->order($order, 'Lavorazioni completate: si aspetta l\'OK del cliente sugli extra', $user);
            }

            return;
        }
        $order->setStatus(WorkOrder::READY)->setReadyAt(new \DateTime())->setWaitingReason(null);
        $this->logger->order($order, 'Tutte le lavorazioni completate: auto pronta', $user);
    }

    /** Cambio di stato manuale (bacheca, scheda ordine) */
    public function setOrderStatus(WorkOrder $order, string $status, ?User $user, ?string $reason = null): void
    {
        if (!isset(WorkOrder::STATUSES[$status]) || $status === $order->getStatus()) {
            return;
        }
        if ($status === WorkOrder::DELIVERED || $status === WorkOrder::CANCELLED) {
            // nessuno può restare a lavorarci sopra
            foreach ($order->getTasks() as $task) {
                if ($task->getStatus() === Task::RUNNING) {
                    $this->pause($task, $status === WorkOrder::DELIVERED ? 'Auto consegnata' : 'Ordine annullato', $user);
                }
            }
        }
        $order->setStatus($status)->setWaitingReason($status === WorkOrder::WAITING ? $reason : null);
        match ($status) {
            WorkOrder::READY => $order->setReadyAt(new \DateTime()),
            WorkOrder::DELIVERED => $order->setDeliveredAt(new \DateTime()),
            default => null,
        };
        $this->logger->order($order, 'Stato: '.$order->getStatusLabel().($reason ? ' ('.$reason.')' : ''), $user);
    }
}
