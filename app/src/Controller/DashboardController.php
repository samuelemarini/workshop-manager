<?php

namespace App\Controller;

use App\Entity\Part;
use App\Entity\Task;
use App\Entity\WorkOrder;
use App\Repository\MechanicRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\WorkOrderRepository;
use App\Service\Dates;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AppController
{
    #[Route('/', name: 'homepage')]
    public function index(WorkOrderRepository $orders, TaskRepository $tasks, MechanicRepository $mechanics, TimeEntryRepository $entries): Response
    {
        // l'account del tablet vede solo la schermata tablet
        if (!$this->isGranted('ROLE_OFFICE')) {
            return $this->redirectToRoute('tablet');
        }

        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $open = $orders->findBoard();
        $byStatus = fn (string $s) => array_values(array_filter($open, fn (WorkOrder $o) => $o->getStatus() === $s));

        // meccanici adesso
        $workedToday = $entries->secondsByMechanic($today, $today->modify('+1 day'));
        $cards = [];
        $capacity = 0;
        foreach ($mechanics->findActive() as $m) {
            $list = $tasks->findForMechanic($m, $today);
            $current = null;
            foreach ($list as $t) {
                if ($t->getStatus() === Task::RUNNING) {
                    $current = $t;
                    break;
                }
                if ($t->getStatus() === Task::PAUSED && !$current) {
                    $current = $t;
                }
            }
            $todo = array_values(array_filter($list, fn (Task $t) => $t->getStatus() === Task::TODO));
            $capacity += $m->getDailyHours() * 3600;
            $cards[] = [
                'mechanic' => $m,
                'current' => $current,
                'next' => $todo[0] ?? null,
                'todoCount' => count($todo),
                'worked' => $workedToday[$m->getId()] ?? 0,
            ];
        }

        // consegne promesse per oggi (anche già pronte o consegnate)
        $deliveries = array_values(array_filter($open, fn (WorkOrder $o) => $o->isPromisedOn($today)));
        usort($deliveries, fn ($a, $b) => $a->getPromisedAt() <=> $b->getPromisedAt());

        // cose da gestire
        $alerts = [];
        foreach ($tasks->findProposed() as $t) {
            $alerts[] = ['tone' => $t->isUrgent() ? 'red' : 'orange', 'icon' => $t->isUrgent() ? 'fa-triangle-exclamation' : 'fa-phone', 'order' => $t->getWorkOrder(),
                'title' => $t->getWorkOrder()->getCode().' · '.($t->isUrgent() ? 'problema di sicurezza' : 'serve l\'OK del cliente'),
                'text' => $t->getTitle().' · segnalato da '.$t->getProposedBy()?->getName().' alle '.$t->getProposedAt()?->format('H:i'), 'action' => 'Decidi'];
        }
        foreach ($open as $o) {
            if ($o->isLate($now)) {
                $alerts[] = ['tone' => 'red', 'icon' => 'fa-clock', 'order' => $o, 'title' => $o->getCode().' · consegna in ritardo',
                    'text' => $o->getVehicle()->getName().' di '.$o->getCustomer()->getName().', promessa '.Dates::when($o->getPromisedAt()), 'action' => 'Apri'];
            } elseif ($o->hasUnassignedTasks() && $o->getPromisedAt() && $o->getPromisedAt() < $today->modify('+2 days')) {
                $alerts[] = ['tone' => 'red', 'icon' => 'fa-user-slash', 'order' => $o, 'title' => $o->getCode().' senza meccanico',
                    'text' => 'Consegna promessa '.Dates::when($o->getPromisedAt()).': assegna le lavorazioni dal planning.', 'action' => 'Pianifica'];
            }
            foreach ($o->getParts() as $p) {
                if ($p->isMissing()) {
                    $alerts[] = ['tone' => 'grey', 'icon' => 'fa-box', 'order' => $o, 'title' => $o->getCode().' · '.($p->getStatus() === Part::TO_ORDER ? 'ricambio da ordinare' : 'ricambio in arrivo'),
                        'text' => $p->getDescription().($p->getExpectedAt() ? ' · previsto '.Dates::when($p->getExpectedAt(), false) : '').($p->getSupplier() ? ' ('.$p->getSupplier().')' : ''), 'action' => 'Apri'];
                }
            }
        }

        // settimana in corso: ore lavorate e stimato / effettivo sulle lavorazioni chiuse
        $monday = $today->modify('monday this week');
        $weekWorked = $entries->secondsByMechanic($monday, $now);
        $weekDone = $tasks->findCompletedBetween($monday, $monday->modify('+7 days'));
        $week = [];
        foreach ($mechanics->findActive() as $m) {
            $mine = array_filter($weekDone, fn (Task $t) => $t->getMechanic() === $m);
            $week[] = [
                'mechanic' => $m,
                'worked' => $weekWorked[$m->getId()] ?? 0,
                'capacity' => (int) round($m->getDailyHours() * 3600 * min(5, (int) $now->format('N'))),
                'done' => count($mine),
                'estimated' => array_sum(array_map(fn (Task $t) => $t->getEstimatedMinutes() * 60, $mine)),
                'spent' => array_sum(array_map(fn (Task $t) => $t->getWorkedSeconds(), $mine)),
            ];
        }

        $running = 0;
        foreach ($cards as $c) {
            $running += $c['current']?->getStatus() === Task::RUNNING ? 1 : 0;
        }

        return $this->render('dashboard/index.html.twig', [
            'now' => $now,
            'open' => $open,
            'cards' => $cards,
            'stats' => [
                'inShop' => count($open),
                'running' => $running,
                'waiting' => $byStatus(WorkOrder::WAITING),
                'ready' => $byStatus(WorkOrder::READY),
                'worked' => array_sum($workedToday),
                'capacity' => $capacity,
            ],
            'deliveries' => $deliveries,
            'alerts' => $alerts,
            'week' => $week,
            'monday' => $monday,
        ]);
    }
}
