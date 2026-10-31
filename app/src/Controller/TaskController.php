<?php

namespace App\Controller;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Entity\WorkOrder;
use App\Form\TaskType;
use App\Repository\MechanicRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use App\Service\ActivityLogger;
use App\Service\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lavorazioni dall'ufficio: aggiunta, modifica, OK del cliente sui lavori extra, chiusura a mano,
 * e le stesse azioni del tablet (avvio, pausa, controlli, note) per conto di un meccanico.
 */
#[IsGranted('ROLE_OFFICE')]
class TaskController extends AppController
{
    #[Route('/lavori', name: 'jobs')]
    public function jobs(TaskRepository $tasks, MechanicRepository $mechanics, TimeEntryRepository $entries): Response
    {
        $list = $tasks->findWorkshop();
        $today = new \DateTimeImmutable('today');
        $sections = [];
        foreach ([Task::RUNNING, Task::PAUSED, Task::TODO] as $status) {
            $sections[$status] = ['label' => $this->trans(Task::STATUSES[$status]), 'tasks' => array_values(array_filter($list, fn (Task $t) => $t->getStatus() === $status))];
        }

        return $this->render('task/jobs.html.twig', [
            'sections' => $sections,
            'mechanics' => $mechanics->findActive(),
            'busy' => $this->busy($entries),
            'worked' => $entries->secondsByMechanic($today, $today->modify('+1 day')),
        ]);
    }

    /** Avvio o ripresa per conto di un meccanico: quello che stava facendo va in pausa */
    #[Route('/tasks/{id}/start', name: 'task_start', requirements: ['id' => '\d+'])]
    public function start(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em, MechanicRepository $mechanics, TimeEntryRepository $entries): Response
    {
        if ($request->isMethod('POST')) {
            $this->checkToken($request);
            $mechanic = $em->find(Mechanic::class, $request->request->getInt('mechanic'));
            if (!$mechanic || $mechanic->getCancel()) {
                $this->addFlash('danger', $this->trans('Scegli il meccanico che ci lavora.'));

                return $this->back($request, 'jobs');
            }
            try {
                if (!$task->getOpenEntry()) {
                    $task->setMechanic($mechanic);
                }
                $workflow->start($task, $mechanic, $this->user());
                $em->flush();
                $this->addFlash('success', $this->trans('%task%: in corso con %mechanic%', ['%task%' => $task->getTitle(), '%mechanic%' => $mechanic->getName()]));
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }

            return $this->back($request, 'jobs');
        }

        return $this->render('task/_start.html.twig', [
            'task' => $task,
            'mechanics' => $mechanics->findActive(),
            'busy' => $this->busy($entries),
        ]);
    }

    #[Route('/tasks/{id}/pause', name: 'task_pause', requirements: ['id' => '\d+'])]
    public function pause(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $this->checkToken($request);
            $reason = (string) $request->request->get('reason');
            $workflow->pause($task, isset(Task::PAUSE_REASONS[$reason]) ? $reason : 'Altro', $this->user());
            $em->flush();
            $this->addFlash('success', $this->trans('%task%: in pausa', ['%task%' => $task->getTitle()]));

            return $this->back($request, 'jobs');
        }

        return $this->render('task/_pause.html.twig', ['task' => $task, 'reasons' => Task::PAUSE_REASONS]);
    }

    /** Scheda lavoro: controlli spuntati, note per l'ufficio e km letti, come sul tablet */
    #[Route('/tasks/{id}/work', name: 'task_work', requirements: ['id' => '\d+'])]
    public function work(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $this->checkToken($request);
            $task->markChecks(array_map('intval', $request->request->all('checks')));
            $task->setNotes(trim((string) $request->request->get('notes')) ?: null);
            $km = (int) preg_replace('/\D/', '', (string) $request->request->get('km'));
            if ($km > 0) {
                $task->getWorkOrder()->readKm($km);
            }
            $em->flush();
            $this->addFlash('success', $this->trans('Lavorazione salvata'));

            return $this->back($request, 'jobs');
        }

        return $this->render('task/_work.html.twig', ['task' => $task]);
    }

    /** @return array<int, Task> id meccanico => lavorazione su cui sta lavorando adesso */
    private function busy(TimeEntryRepository $entries): array
    {
        $busy = [];
        foreach ($entries->findBy(['endedAt' => null]) as $entry) {
            $busy[$entry->getMechanic()->getId()] = $entry->getTask();
        }

        return $busy;
    }

    #[Route('/orders/{id}/tasks/new', name: 'task_new', requirements: ['id' => '\d+'])]
    public function new(Request $request, WorkOrder $order, EntityManagerInterface $em, ActivityLogger $logger, Workflow $workflow): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $order->addTask($task);
            // una lavorazione nuova su un'auto già pronta la rimette in lavorazione
            if ($order->getStatus() === WorkOrder::READY) {
                $workflow->setOrderStatus($order, $order->getWorkedSeconds() ? WorkOrder::IN_PROGRESS : WorkOrder::ACCEPTED, $this->user());
            }
            $em->persist($task);
            $logger->order($order, 'Lavorazione aggiunta: %task%', $this->user(), params: ['%task%' => $task->getTitle()]);
            $em->flush();
            $this->addFlash('success', $this->trans('Lavorazione aggiunta'));

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('task/_form.html.twig', ['form' => $form, 'task' => $task, 'order' => $order]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', $this->trans('Lavorazione salvata'));

            return $this->redirectToRoute('order_show', ['id' => $task->getWorkOrder()->getId()]);
        }

        return $this->render('task/_form.html.twig', ['form' => $form, 'task' => $task, 'order' => $task->getWorkOrder()]);
    }

    #[Route('/tasks/{id}/delete', name: 'task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $em, ActivityLogger $logger, Workflow $workflow): Response
    {
        $this->checkToken($request);
        $order = $task->getWorkOrder();
        if ($task->getTimeEntries()->count()) {
            $this->addFlash('danger', $this->trans('La lavorazione ha tempi registrati: non si può eliminare.'));
        } else {
            $order->removeTask($task);
            $logger->order($order, 'Lavorazione eliminata: %task%', $this->user(), params: ['%task%' => $task->getTitle()]);
            $workflow->syncOrder($order, $this->user());
            $em->flush();
            $this->addFlash('success', $this->trans('Lavorazione eliminata'));
        }

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    /** Risposta del cliente a un lavoro extra segnalato dal meccanico */
    #[Route('/tasks/{id}/decide', name: 'task_decide', methods: ['POST'])]
    public function decide(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $approved = $request->request->getBoolean('approved');
        $workflow->decide($task, $approved, $this->user());
        $em->flush();
        $this->addFlash('success', match (true) {
            !$approved => $this->trans('Lavorazione rifiutata'),
            $task->getMechanic() !== null => $this->trans('Autorizzata: %mechanic% la trova sul tablet', ['%mechanic%' => $task->getMechanic()->getName()]),
            default => $this->trans('Autorizzata: il meccanico la trova sul tablet'),
        });

        return $this->back($request, 'order_show', ['id' => $task->getWorkOrder()->getId()]);
    }

    #[Route('/tasks/{id}/finish', name: 'task_finish', methods: ['POST'])]
    public function finish(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $workflow->finish($task, $this->user());
        $em->flush();
        $this->addFlash('success', $this->trans('Lavorazione completata'));

        return $this->back($request, 'order_show', ['id' => $task->getWorkOrder()->getId()]);
    }

    #[Route('/tasks/{id}/reopen', name: 'task_reopen', methods: ['POST'])]
    public function reopen(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $workflow->reopen($task, $this->user());
        $em->flush();

        return $this->back($request, 'order_show', ['id' => $task->getWorkOrder()->getId()]);
    }
}
