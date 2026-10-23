<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\WorkOrder;
use App\Form\TaskType;
use App\Service\ActivityLogger;
use App\Service\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lavorazioni dall'ufficio: aggiunta, modifica, OK del cliente sui lavori extra, chiusura a mano.
 */
#[IsGranted('ROLE_OFFICE')]
class TaskController extends AppController
{
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
            $logger->order($order, 'Lavorazione aggiunta: '.$task->getTitle(), $this->user());
            $em->flush();
            $this->addFlash('success', 'Lavorazione aggiunta');

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
            $this->addFlash('success', 'Lavorazione salvata');

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
            $this->addFlash('danger', 'La lavorazione ha tempi registrati: non si può eliminare.');
        } else {
            $order->removeTask($task);
            $logger->order($order, 'Lavorazione eliminata: '.$task->getTitle(), $this->user());
            $workflow->syncOrder($order, $this->user());
            $em->flush();
            $this->addFlash('success', 'Lavorazione eliminata');
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
        $this->addFlash('success', $approved ? 'Autorizzata: '.($task->getMechanic()?->getName() ?? 'il meccanico').' la trova sul tablet' : 'Lavorazione rifiutata');

        return $this->back($request, 'order_show', ['id' => $task->getWorkOrder()->getId()]);
    }

    #[Route('/tasks/{id}/finish', name: 'task_finish', methods: ['POST'])]
    public function finish(Request $request, Task $task, Workflow $workflow, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $workflow->finish($task, $this->user());
        $em->flush();
        $this->addFlash('success', 'Lavorazione completata');

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
