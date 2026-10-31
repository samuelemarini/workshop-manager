<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Form\VehicleType;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vehicles')]
#[IsGranted('ROLE_OFFICE')]
class VehicleController extends AppController
{
    #[Route('', name: 'vehicle_index')]
    public function index(VehicleRepository $vehicles): Response
    {
        return $this->render('vehicle/index.html.twig', ['vehicles' => $vehicles->findActive()]);
    }

    /** Scheda con lo storico degli interventi */
    #[Route('/{id}', name: 'vehicle_show', requirements: ['id' => '\d+'])]
    public function show(Vehicle $vehicle): Response
    {
        return $this->render('vehicle/show.html.twig', ['vehicle' => $vehicle]);
    }

    #[Route('/new', name: 'vehicle_new')]
    #[Route('/{id}/edit', name: 'vehicle_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, EntityManagerInterface $em, ?Vehicle $vehicle = null): Response
    {
        if (!$vehicle) {
            $vehicle = (new Vehicle())->setOwner($em->find(Customer::class, $request->query->getInt('owner')));
        }
        $form = $this->createForm(VehicleType::class, $vehicle);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($vehicle);
            $em->flush();
            $this->addFlash('success', $this->trans('Veicolo salvato'));

            return $this->redirectToRoute('vehicle_show', ['id' => $vehicle->getId()]);
        }

        return $this->render('vehicle/edit.html.twig', ['form' => $form, 'vehicle' => $vehicle]);
    }

    #[Route('/{id}/delete', name: 'vehicle_delete', methods: ['POST'])]
    public function delete(Request $request, Vehicle $vehicle, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        // la targa resta libera per un eventuale nuovo inserimento
        $vehicle->setCancel(1)->setPlate($vehicle->getPlate().'X'.$vehicle->getId());
        $em->flush();
        $this->addFlash('success', $this->trans('Veicolo eliminato'));

        return $this->redirectToRoute('vehicle_index');
    }
}
