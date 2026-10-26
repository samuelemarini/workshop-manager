<?php

namespace App\Controller;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\SettingRepository;
use App\Repository\TaskRepository;
use App\Service\ActivityLogger;
use App\Service\CalendarPlanner;
use App\Service\DayPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OFFICE')]
class PlanningController extends AppController
{
    #[Route('/planning', name: 'planning')]
    public function index(Request $request, DayPlanner $planner, TaskRepository $tasks): Response
    {
        $day = $this->dateParam($request->query->get('day'), 'today');
        if (in_array($request->query->get('mode'), ['week', 'month'], true)) {
            return $this->redirectToRoute('planning_calendar', $request->query->all());
        }
        $monday = $day->modify('monday this week');

        return $this->render('planning/index.html.twig', [
            'day' => $day,
            'grid' => $planner->day($day),
            'backlog' => $tasks->findUnscheduled(),
            'days' => array_map(fn (int $i) => $monday->modify("+$i days"), range(0, 5)),
            'today' => new \DateTimeImmutable('today'),
        ]);
    }

    /**
     * Calendario dei lavori: settimana (righe = meccanici, colonne = giorni) o mese (griglia classica).
     */
    #[Route('/planning/calendar', name: 'planning_calendar')]
    public function calendar(Request $request, CalendarPlanner $calendar): Response
    {
        $date = $this->dateParam($request->query->get('day'), 'today');
        $mode = $request->query->get('mode') === 'month' ? 'month' : 'week';
        if ($mode === 'week') {
            $from = $date->modify('monday this week');
            $to = $from->modify('+7 days');
        } else {
            $first = $date->modify('first day of this month');
            $from = $first->modify('monday this week');
            $to = $first->modify('last day of this month')->modify('sunday this week')->modify('+1 day');
        }
        $data = $calendar->range($from, $to);
        // la domenica si vede solo se c'è qualcosa
        $sunday = $from->modify('+6 days')->format('Y-m-d');
        $showSunday = $mode === 'month' || $data['days'][$sunday]['items'] || $data['days'][$sunday]['deliveries'];

        return $this->render('planning/calendar.html.twig', [
            'mode' => $mode,
            'day' => $date,
            'from' => $from,
            'to' => $to->modify('-1 day'),
            'days' => $showSunday ? $data['days'] : array_filter($data['days'], fn ($d) => $d['date']->format('N') !== '7'),
            'mechanics' => $data['mechanics'],
            'unassigned' => array_reduce($data['days'], fn ($c, $d) => $c || array_filter($d['items'], fn ($i) => $i['mechanicKey'] === 0), false),
            'today' => new \DateTimeImmutable('today'),
            'prev' => $mode === 'week' ? $from->modify('-7 days') : $date->modify('first day of previous month'),
            'next' => $mode === 'week' ? $from->modify('+7 days') : $date->modify('first day of next month'),
        ]);
    }

    /**
     * Modale "Pianifica": meccanico, orario e durata. Le altre lavorazioni dell'ordine ancora
     * senza orario si possono accodare: stesso meccanico, ognuna parte quando finisce la precedente.
     */
    #[Route('/tasks/{id}/plan', name: 'task_plan', requirements: ['id' => '\d+'])]
    public function plan(Request $request, Task $task, EntityManagerInterface $em, ActivityLogger $logger, SettingRepository $settings): Response
    {
        $others = array_values(array_filter($task->getWorkOrder()->getTasks()->toArray(), fn (Task $t) => $t !== $task
            && !$t->getPlannedStart() && in_array($t->getStatus(), [Task::TODO, Task::PAUSED], true)));
        // accodate già spuntate se si sta pianificando una lavorazione nuova, non se si sposta una già in planning
        $chainDefault = !$task->getPlannedStart();
        if (!$task->getPlannedStart() && $request->query->get('day')) {
            $start = $this->entityDate($request->query->get('day'), 'today');
            $task->setPlannedStart($start->setTime(0, $settings->minutes('day_start')));
        }
        $form = $this->createForm(TaskType::class, $task, ['plan_only' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->order($task->getWorkOrder(), 'Pianificata: '.$task->getTitle().$this->planText($task), $this->user());
            $count = 1;
            if ($task->getPlannedStart()) {
                $ids = array_map('intval', $request->request->all('chain'));
                $previous = $task;
                foreach ($others as $next) {
                    if (!in_array($next->getId(), $ids, true)) {
                        continue;
                    }
                    $next->setMechanic($task->getMechanic())->setPlannedStart($this->after($previous, $settings));
                    $logger->order($task->getWorkOrder(), 'Pianificata: '.$next->getTitle().$this->planText($next), $this->user());
                    $previous = $next;
                    $count++;
                }
            }
            $em->flush();
            $this->addFlash('success', $count > 1 ? "$count lavorazioni pianificate una dopo l'altra" : 'Lavorazione pianificata');
            // dalla scheda ordine o dai lavori in officina si torna lì, dal planning al giorno pianificato
            $referer = (string) $request->headers->get('referer');
            if (str_contains($referer, '/orders/')) {
                return $this->redirectToRoute('order_show', ['id' => $task->getWorkOrder()->getId()]);
            }
            if (str_contains($referer, '/lavori')) {
                return $this->redirectToRoute('jobs');
            }

            return $this->redirectToRoute('planning', ['day' => ($task->getPlannedStart() ?? new \DateTime())->format('Y-m-d')]);
        }

        return $this->render('planning/_plan_form.html.twig', ['form' => $form, 'task' => $task, 'others' => $others, 'chainDefault' => $chainDefault]);
    }

    /**
     * Inizio della lavorazione che segue: alla fine della precedente; se cade nella pausa pranzo
     * si parte alla riapertura, se cade dopo la chiusura il giorno lavorativo dopo all'apertura.
     */
    private function after(Task $previous, SettingRepository $settings): \DateTime
    {
        $start = \DateTime::createFromInterface($previous->getPlannedStart())->modify('+'.$previous->getEstimatedMinutes().' minutes');
        $minute = (int) $start->format('G') * 60 + (int) $start->format('i');
        if ($minute >= $settings->minutes('lunch_start') && $minute < $settings->minutes('lunch_end')) {
            $start->setTime(0, $settings->minutes('lunch_end'));
        } elseif ($minute >= $settings->minutes('day_end')) {
            do {
                $start->modify('+1 day');
            } while ($start->format('N') === '7');
            $start->setTime(0, $settings->minutes('day_start'));
        }

        return $start;
    }

    /** Trascinamento sul planning: nuovo orario e nuovo meccanico */
    #[Route('/planning/move', name: 'planning_move', methods: ['POST'])]
    public function move(Request $request, EntityManagerInterface $em, ActivityLogger $logger): JsonResponse
    {
        $this->checkToken($request);
        $task = $em->find(Task::class, $request->request->getInt('task'));
        $start = \DateTime::createFromFormat('Y-m-d H:i', (string) $request->request->get('start'));
        if (!$task || !$start || !in_array($task->getStatus(), [Task::TODO, Task::PAUSED], true)) {
            return new JsonResponse(['error' => 'Questa lavorazione non si può spostare.'], 400);
        }
        $task->setPlannedStart($start);
        if ($request->request->has('mechanic')) {
            $id = (int) $request->request->get('mechanic');
            $task->setMechanic($id ? $em->find(Mechanic::class, $id) : null);
        }
        $logger->order($task->getWorkOrder(), 'Pianificata: '.$task->getTitle().$this->planText($task), $this->user());
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function planText(Task $task): string
    {
        $parts = [];
        if ($task->getPlannedStart()) {
            $parts[] = $task->getPlannedStart()->format('d/m H:i');
        }
        if ($task->getMechanic()) {
            $parts[] = $task->getMechanic()->getName();
        }

        return $parts ? ' ('.implode(', ', $parts).')' : '';
    }
}
