<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Customer;
use App\Entity\Task;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use App\Form\WorkOrderType;
use App\Repository\ActivityLogRepository;
use App\Repository\SettingRepository;
use App\Repository\VehicleRepository;
use App\Repository\WorkOrderRepository;
use App\Service\ActivityLogger;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
#[IsGranted('ROLE_OFFICE')]
class WorkOrderController extends AppController
{
    #[Route('', name: 'order_index')]
    public function index(WorkOrderRepository $orders): Response
    {
        $list = $orders->findAllForList();
        $counts = [];
        foreach ($list as $o) {
            $counts[$o->getStatus()] = ($counts[$o->getStatus()] ?? 0) + 1;
        }

        return $this->render('order/index.html.twig', ['orders' => $list, 'counts' => $counts]);
    }

    /** Accettazione: veicolo e cliente dall'anagrafica o nuovi, lavorazioni da quelle tipo */
    #[Route('/new', name: 'order_new')]
    public function new(Request $request, EntityManagerInterface $em, WorkOrderRepository $orders, VehicleRepository $vehicles, ActivityLogger $logger): Response
    {
        $order = (new WorkOrder())->setCreatedBy($this->user());
        if ($vehicle = $vehicles->find($request->query->getInt('vehicle'))) {
            $order->setVehicle($vehicle)->setCustomer($vehicle->getOwner());
        }
        $form = $this->createForm(WorkOrderType::class, $order, ['acceptance' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->resolveVehicleAndCustomer($form, $order, $vehicles, $em);
        }
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->get('templates')->getData() as $template) {
                $order->addTask(Task::fromTemplate($template));
            }
            if ($other = trim((string) $form->get('otherTask')->getData())) {
                $order->addTask((new Task())->setTitle($other)->setEstimatedMinutes(60));
            }
            $vehicle = $order->getVehicle();
            if ($order->getKmIn() && $order->getKmIn() > (int) $vehicle->getKm()) {
                $vehicle->setKm($order->getKmIn());
            }
            $order->setNumber($orders->nextNumber($order->getYear()));
            $em->persist($order);
            $em->flush();
            $logger->order($order, 'Accettazione: '.$vehicle->getPlateLabel().' di '.$order->getCustomer()->getName(), $this->user());
            $em->flush();
            $this->addFlash('success', $order->getCode().' creato: assegna e pianifica le lavorazioni');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('order/new.html.twig', ['form' => $form, 'order' => $order]);
    }

    private function resolveVehicleAndCustomer(FormInterface $form, WorkOrder $order, VehicleRepository $vehicles, EntityManagerInterface $em): void
    {
        $vehicle = $order->getVehicle();
        if (!$vehicle) {
            $plate = Vehicle::normalizePlate($form->get('newPlate')->getData());
            $vehicle = $plate ? $vehicles->findOneByPlate($plate) : null;
            if (!$plate) {
                $form->get('newPlate')->addError(new FormError('Scegli un veicolo dall\'elenco o scrivi la targa'));
            } elseif (!$vehicle) {
                $make = trim((string) $form->get('newMake')->getData());
                $model = trim((string) $form->get('newModel')->getData());
                if ($make === '' || $model === '') {
                    $form->get($make === '' ? 'newMake' : 'newModel')->addError(new FormError('Serve per il veicolo nuovo'));
                } else {
                    $vehicle = (new Vehicle())->setPlate($plate)->setMake($make)->setModel($model)
                        ->setVersion($form->get('newVersion')->getData())->setYear($form->get('newYear')->getData());
                    $em->persist($vehicle);
                }
            }
            $order->setVehicle($vehicle);
        }

        $customer = $order->getCustomer();
        if (!$customer) {
            $name = trim((string) $form->get('newCustomerName')->getData());
            if ($name !== '') {
                $customer = (new Customer())->setName($name)->setPhone($form->get('newCustomerPhone')->getData());
                $em->persist($customer);
            } else {
                $customer = $vehicle?->getOwner();
            }
            if (!$customer) {
                $form->get('newCustomerName')->addError(new FormError('Scegli un cliente dall\'elenco o scrivi il nome'));
            }
            $order->setCustomer($customer);
        }
        if ($vehicle && $customer && !$vehicle->getOwner()) {
            $vehicle->setOwner($customer);
        }
    }

    /** Ricerca targa in accettazione: se il veicolo esiste lo si sceglie dall'elenco */
    #[Route('/plate-lookup', name: 'order_plate_lookup')]
    public function plateLookup(Request $request, VehicleRepository $vehicles): JsonResponse
    {
        $vehicle = $vehicles->findOneByPlate($request->query->get('plate'));

        // new JsonResponse(null) manderebbe {}, non null
        return $vehicle
            ? new JsonResponse(['id' => $vehicle->getId(), 'label' => (string) $vehicle, 'owner' => $vehicle->getOwner()?->getId()])
            : JsonResponse::fromJsonString('null');
    }

    #[Route('/{id}', name: 'order_show', requirements: ['id' => '\d+'])]
    public function show(WorkOrder $order, ActivityLogRepository $logs, SettingRepository $settings): Response
    {
        $entries = [];
        foreach ($order->getTasks() as $task) {
            foreach ($task->getTimeEntries() as $e) {
                $entries[] = $e;
            }
        }
        usort($entries, fn ($a, $b) => $b->getStartedAt() <=> $a->getStartedAt());

        return $this->render('order/show.html.twig', [
            'order' => $order,
            'entries' => $entries,
            'history' => $logs->findFor(ActivityLog::SUBJECT_ORDER, $order->getId()),
            'rate' => $settings->labourRate(),
            'now' => new \DateTimeImmutable(),
        ]);
    }

    #[Route('/{id}/edit', name: 'order_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, WorkOrder $order, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $form = $this->createForm(WorkOrderType::class, $order);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->order($order, 'Dati dell\'ordine modificati', $this->user());
            $em->flush();
            $this->addFlash('success', 'Ordine salvato');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        return $this->render('order/edit.html.twig', ['form' => $form, 'order' => $order]);
    }

    /** Note interne modificate al volo dalla scheda */
    #[Route('/{id}/notes', name: 'order_notes', methods: ['POST'])]
    public function notes(Request $request, WorkOrder $order, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $order->setInternalNotes(trim((string) $request->request->get('notes')) ?: null);
        $em->flush();
        $this->addFlash('success', 'Note salvate');

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/notified', name: 'order_notified', methods: ['POST'])]
    public function notified(Request $request, WorkOrder $order, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        $this->checkToken($request);
        $order->setCustomerNotifiedAt(new \DateTime());
        $logger->order($order, 'Cliente avvisato'.($order->getCustomer()->getContactPreference() ? ' ('.$order->getCustomer()->getContactPreference().')' : ''), $this->user());
        $em->flush();
        $this->addFlash('success', 'Segnato: cliente avvisato');

        return $this->back($request, 'order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/upload', name: 'order_upload', methods: ['POST'])]
    public function upload(Request $request, WorkOrder $order, AttachmentStorage $storage, EntityManagerInterface $em): Response
    {
        $this->storeUploads($request, $storage, $em, fn ($a) => $a->setWorkOrder($order));

        return $this->redirect($this->generateUrl('order_show', ['id' => $order->getId()]).'#foto');
    }

    #[Route('/{id}/print', name: 'order_print', requirements: ['id' => '\d+'])]
    public function print(WorkOrder $order, SettingRepository $settings): Response
    {
        return $this->render('order/print.html.twig', ['order' => $order, 'rate' => $settings->labourRate()]);
    }

    /** Solo ordini senza tempi registrati (aperti per errore); gli altri si annullano */
    #[Route('/{id}/delete', name: 'order_delete', methods: ['POST'])]
    public function delete(Request $request, WorkOrder $order, EntityManagerInterface $em, AttachmentStorage $storage): Response
    {
        $this->checkToken($request);
        if ($order->getWorkedSeconds() > 0) {
            $this->addFlash('danger', 'L\'ordine ha tempi registrati: annullalo invece di eliminarlo.');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }
        foreach ($order->getAttachments() as $a) {
            $storage->delete($a);
        }
        $em->remove($order);
        $em->flush();
        $this->addFlash('success', 'Ordine eliminato');

        return $this->redirectToRoute('board');
    }
}
