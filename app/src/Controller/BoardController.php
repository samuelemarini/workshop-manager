<?php

namespace App\Controller;

use App\Entity\WorkOrder;
use App\Repository\MechanicRepository;
use App\Repository\WorkOrderRepository;
use App\Service\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OFFICE')]
class BoardController extends AppController
{
    private const COLUMNS = [
        WorkOrder::ACCEPTED => '#8c8485',
        WorkOrder::IN_PROGRESS => '#c8102e',
        WorkOrder::WAITING => '#d98a00',
        WorkOrder::READY => '#1f8a5a',
        WorkOrder::DELIVERED => '#d9d4d3',
    ];

    #[Route('/board', name: 'board')]
    public function index(WorkOrderRepository $orders, MechanicRepository $mechanics): Response
    {
        $list = $orders->findBoard(new \DateTimeImmutable('-7 days'));
        $columns = [];
        foreach (self::COLUMNS as $status => $color) {
            $columns[$status] = [
                'label' => $status === WorkOrder::DELIVERED ? 'Consegnate (7 gg)' : WorkOrder::STATUSES[$status],
                'color' => $color,
                'orders' => array_values(array_filter($list, fn (WorkOrder $o) => $o->getStatus() === $status)),
            ];
        }

        return $this->render('board/index.html.twig', ['columns' => $columns, 'mechanics' => $mechanics->findActive(), 'count' => count($list) - count($columns[WorkOrder::DELIVERED]['orders'])]);
    }

    /** Trascinamento di una scheda in un'altra colonna (anche dalla scheda ordine, senza JavaScript) */
    #[Route('/orders/{id}/status', name: 'order_status', methods: ['POST'])]
    public function status(Request $request, WorkOrder $order, Workflow $workflow, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $status = (string) $request->request->get('status');
        if (!isset(WorkOrder::STATUSES[$status])) {
            throw $this->createNotFoundException();
        }
        $workflow->setOrderStatus($order, $status, $this->user(), trim((string) $request->request->get('reason')) ?: null);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'label' => $order->getStatusLabel()]);
        }
        $this->addFlash('success', $order->getCode().': '.mb_strtolower($order->getStatusLabel()));

        return $this->back($request, 'order_show', ['id' => $order->getId()]);
    }
}
