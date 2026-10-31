<?php

namespace App\Controller;

use App\Entity\Part;
use App\Entity\WorkOrder;
use App\Form\PartType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OFFICE')]
class PartController extends AppController
{
    #[Route('/orders/{id}/parts/new', name: 'part_new', requirements: ['id' => '\d+'])]
    public function new(Request $request, WorkOrder $order, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        return $this->form($request, $order, new Part(), $em, $logger);
    }

    #[Route('/parts/{id}/edit', name: 'part_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Part $part, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        return $this->form($request, $part->getWorkOrder(), $part, $em, $logger);
    }

    private function form(Request $request, WorkOrder $order, Part $part, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $isNew = $part->getId() === null;
        $form = $this->createForm(PartType::class, $part, ['work_order' => $order]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $order->addPart($part);
                $em->persist($part);
                $logger->order($order, 'Ricambio aggiunto: %part%', $this->user(), params: ['%part%' => $part->getDescription()]);
            }
            $em->flush();
            $this->addFlash('success', $this->trans('Ricambio salvato'));

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('part/_form.html.twig', ['form' => $form, 'part' => $part, 'order' => $order]);
    }

    #[Route('/parts/{id}/delete', name: 'part_delete', methods: ['POST'])]
    public function delete(Request $request, Part $part, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $order = $part->getWorkOrder();
        $order->removePart($part);
        $em->flush();
        $this->addFlash('success', $this->trans('Ricambio eliminato'));

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }
}
