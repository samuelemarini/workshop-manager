<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customers')]
#[IsGranted('ROLE_OFFICE')]
class CustomerController extends AppController
{
    #[Route('', name: 'customer_index')]
    public function index(CustomerRepository $customers): Response
    {
        return $this->render('customer/index.html.twig', ['customers' => $customers->findBy(['cancel' => 0], ['name' => 'ASC'])]);
    }

    #[Route('/new', name: 'customer_new')]
    #[Route('/{id}/edit', name: 'customer_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, EntityManagerInterface $em, VehicleRepository $vehicles, ?Customer $customer = null): Response
    {
        $customer ??= new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($customer);
            $em->flush();
            $this->addFlash('success', $this->trans('Cliente salvato'));

            return $this->redirectToRoute('customer_index');
        }

        return $this->render('customer/edit.html.twig', [
            'form' => $form,
            'customer' => $customer,
            'vehicles' => $customer->getId() ? $vehicles->findBy(['owner' => $customer, 'cancel' => 0], ['plate' => 'ASC']) : [],
        ]);
    }

    #[Route('/{id}/delete', name: 'customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $customer->setCancel(1);
        $em->flush();
        $this->addFlash('success', $this->trans('Cliente eliminato'));

        return $this->redirectToRoute('customer_index');
    }
}
