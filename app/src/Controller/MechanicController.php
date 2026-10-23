<?php

namespace App\Controller;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Form\MechanicType;
use App\Repository\MechanicRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mechanics')]
#[IsGranted('ROLE_OFFICE')]
class MechanicController extends AppController
{
    /** Squadra e ore della settimana: lavorate per giorno, stimato contro effettivo sulle lavorazioni chiuse */
    #[Route('', name: 'mechanic_index')]
    public function index(Request $request, MechanicRepository $mechanics, TimeEntryRepository $entries, TaskRepository $tasks): Response
    {
        $monday = $this->dateParam($request->query->get('week'), 'today')->modify('monday this week');
        $days = array_map(fn (int $i) => $monday->modify("+$i days"), range(0, 5));
        $list = $mechanics->findActive();

        $hours = [];
        foreach ($days as $day) {
            foreach ($entries->secondsByMechanic($day, $day->modify('+1 day')) as $id => $seconds) {
                $hours[$id][$day->format('Y-m-d')] = $seconds;
            }
        }
        $done = $tasks->findCompletedBetween($monday, $monday->modify('+7 days'));
        $rows = [];
        foreach ($list as $m) {
            $mine = array_values(array_filter($done, fn (Task $t) => $t->getMechanic() === $m));
            $estimated = array_sum(array_map(fn (Task $t) => $t->getEstimatedMinutes() * 60, $mine));
            $spent = array_sum(array_map(fn (Task $t) => $t->getWorkedSeconds(), $mine));
            $rows[] = [
                'mechanic' => $m,
                'days' => $hours[$m->getId()] ?? [],
                'total' => array_sum($hours[$m->getId()] ?? []),
                'capacity' => (int) round($m->getDailyHours() * 3600 * 5),
                'done' => count($mine),
                'estimated' => $estimated,
                'spent' => $spent,
                // >100% = più veloce della stima
                'efficiency' => $spent ? (int) round($estimated / $spent * 100) : null,
            ];
        }

        return $this->render('mechanic/index.html.twig', [
            'monday' => $monday,
            'days' => $days,
            'rows' => $rows,
            'done' => $done,
        ]);
    }

    #[Route('/new', name: 'mechanic_new')]
    #[Route('/{id}/edit', name: 'mechanic_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, EntityManagerInterface $em, ?Mechanic $mechanic = null): Response
    {
        $mechanic ??= new Mechanic();
        $form = $this->createForm(MechanicType::class, $mechanic);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('removePin') && $form->get('removePin')->getData()) {
                $mechanic->setPin(null);
            } elseif ($pin = $form->get('pin')->getData()) {
                $mechanic->setPin($pin);
            }
            $em->persist($mechanic);
            $em->flush();
            $this->addFlash('success', 'Meccanico salvato');

            return $this->redirectToRoute('mechanic_index');
        }

        return $this->render('mechanic/_form.html.twig', ['form' => $form, 'mechanic' => $mechanic]);
    }

    /** Non si cancella: le ore registrate restano nello storico */
    #[Route('/{id}/delete', name: 'mechanic_delete', methods: ['POST'])]
    public function delete(Request $request, Mechanic $mechanic, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $mechanic->setCancel(1);
        $em->flush();
        $this->addFlash('success', $mechanic->getName().' non compare più su planning e tablet');

        return $this->redirectToRoute('mechanic_index');
    }
}
