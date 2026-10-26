<?php

namespace App\Controller;

use App\Entity\Mechanic;
use App\Entity\Part;
use App\Entity\Task;
use App\Repository\MechanicRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use App\Service\ActivityLogger;
use App\Service\AttachmentStorage;
use App\Service\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tablet in officina. Il tablet è collegato con un utente "Tablet officina";
 * il meccanico si sceglie toccando il proprio nome (PIN facoltativo) e resta in sessione
 * finché non tocca "Cambia operatore" o il tablet resta fermo qualche minuto.
 */
#[Route('/tablet')]
#[IsGranted('ROLE_TABLET')]
class TabletController extends AppController
{
    private const SESSION_KEY = 'tablet_mechanic';

    public function __construct(
        private EntityManagerInterface $em,
        private Workflow $workflow,
        private TaskRepository $tasks,
    ) {
    }

    private function mechanic(Request $request): ?Mechanic
    {
        $id = $request->getSession()->get(self::SESSION_KEY);
        $mechanic = $id ? $this->em->find(Mechanic::class, $id) : null;

        return $mechanic && !$mechanic->getCancel() ? $mechanic : null;
    }

    private function toChoice(): RedirectResponse
    {
        return $this->redirectToRoute('tablet');
    }

    #[Route('', name: 'tablet')]
    public function choose(Request $request, MechanicRepository $mechanics): Response
    {
        if ($this->mechanic($request)) {
            return $this->redirectToRoute('tablet_list');
        }
        $tiles = [];
        foreach ($mechanics->findActive() as $m) {
            $mine = $this->tasks->findForMechanic($m, new \DateTimeImmutable('today'));
            $current = null;
            foreach ($mine as $t) {
                if ($t->getStatus() === Task::RUNNING || ($t->getStatus() === Task::PAUSED && !$current)) {
                    $current = $t;
                }
            }
            $tiles[] = ['mechanic' => $m, 'current' => $current, 'todo' => count(array_filter($mine, fn (Task $t) => $t->getStatus() !== Task::DONE))];
        }

        return $this->render('tablet/choose.html.twig', ['tiles' => $tiles, 'now' => new \DateTimeImmutable()]);
    }

    #[Route('/login/{id}', name: 'tablet_login', methods: ['POST'])]
    public function login(Request $request, Mechanic $mechanic): Response
    {
        $this->checkToken($request);
        if ($mechanic->getCancel() || !$mechanic->checkPin($request->request->get('pin'))) {
            $this->addFlash('danger', 'PIN sbagliato, riprova.');

            return $this->toChoice();
        }
        $request->getSession()->set(self::SESSION_KEY, $mechanic->getId());

        return $this->redirectToRoute('tablet_list');
    }

    #[Route('/logout', name: 'tablet_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->remove(self::SESSION_KEY);

        return $this->toChoice();
    }

    #[Route('/lavori', name: 'tablet_list')]
    public function list(Request $request, TimeEntryRepository $entries): Response
    {
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $today = new \DateTimeImmutable('today');
        $mine = $this->tasks->findForMechanic($me, $today);
        $current = null;
        foreach ($mine as $t) {
            if ($t->getStatus() === Task::RUNNING || ($t->getStatus() === Task::PAUSED && !$current)) {
                $current = $t;
            }
        }

        return $this->render('tablet/list.html.twig', [
            'me' => $me,
            'current' => $current,
            'todo' => array_values(array_filter($mine, fn (Task $t) => $t !== $current && in_array($t->getStatus(), [Task::TODO, Task::PAUSED], true))),
            'done' => array_values(array_filter($mine, fn (Task $t) => $t->getStatus() === Task::DONE)),
            'free' => $this->tasks->findFree(),
            'worked' => $entries->secondsByMechanic($today, $today->modify('+1 day'))[$me->getId()] ?? 0,
            'running' => $entries->findOpen($me) !== null,
        ]);
    }

    #[Route('/lavoro/{id}', name: 'tablet_task', requirements: ['id' => '\d+'])]
    public function task(Request $request, Task $task, TimeEntryRepository $entries): Response
    {
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $today = new \DateTimeImmutable('today');

        return $this->render('tablet/task.html.twig', [
            'me' => $me,
            'task' => $task,
            'tab' => $request->query->get('tab', 'check'),
            'reasons' => Task::PAUSE_REASONS,
            'photos' => array_filter($task->getWorkOrder()->getAttachments()->toArray(), fn ($a) => $a->isImage()),
            'worked' => $entries->secondsByMechanic($today, $today->modify('+1 day'))[$me->getId()] ?? 0,
            'running' => $entries->findOpen($me) !== null,
        ]);
    }

    #[Route('/lavoro/{id}/start', name: 'tablet_start', methods: ['POST'])]
    public function start(Request $request, Task $task): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        try {
            $this->workflow->start($task, $me, $this->user());
            $this->em->flush();
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('tablet_task', ['id' => $task->getId()]);
    }

    #[Route('/lavoro/{id}/pause', name: 'tablet_pause', methods: ['POST'])]
    public function pause(Request $request, Task $task): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $reason = (string) $request->request->get('reason');
        $this->workflow->pause($task, isset(Task::PAUSE_REASONS[$reason]) ? $reason : 'Altro', $this->user(), $me);
        $this->em->flush();

        return $this->redirectToRoute($reason === 'Fine giornata' ? 'tablet_list' : 'tablet_task', ['id' => $task->getId()]);
    }

    #[Route('/lavoro/{id}/finish', name: 'tablet_finish', methods: ['POST'])]
    public function finish(Request $request, Task $task): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $this->workflow->finish($task, $this->user(), $me);
        $this->em->flush();

        return $this->redirectToRoute('tablet_done', ['id' => $task->getId()]);
    }

    #[Route('/lavoro/{id}/fine', name: 'tablet_done', requirements: ['id' => '\d+'])]
    public function done(Request $request, Task $task, TimeEntryRepository $entries): Response
    {
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $next = null;
        foreach ($this->tasks->findForMechanic($me, new \DateTimeImmutable('today')) as $t) {
            if (in_array($t->getStatus(), [Task::TODO, Task::PAUSED], true)) {
                $next = $t;
                break;
            }
        }

        $today = new \DateTimeImmutable('today');

        return $this->render('tablet/done.html.twig', [
            'me' => $me,
            'task' => $task,
            'next' => $next,
            'worked' => $entries->secondsByMechanic($today, $today->modify('+1 day'))[$me->getId()] ?? 0,
            'running' => $entries->findOpen($me) !== null,
        ]);
    }

    /** Lavoro libero (senza meccanico): "Prendo io" */
    #[Route('/lavoro/{id}/take', name: 'tablet_take', methods: ['POST'])]
    public function take(Request $request, Task $task, ActivityLogger $logger): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        if ($task->getMechanic() && $task->getMechanic() !== $me) {
            $this->addFlash('danger', 'L\'ha già presa '.$task->getMechanic()->getName().'.');
        } else {
            $task->setMechanic($me);
            $logger->order($task->getWorkOrder(), 'Presa in carico dal tablet: '.$task->getTitle(), $this->user(), $me);
            $this->em->flush();
            $this->addFlash('success', 'Fatto: la trovi nei tuoi prossimi lavori');
        }

        return $this->redirectToRoute('tablet_list');
    }

    #[Route('/lavoro/{id}/check/{index}', name: 'tablet_check', methods: ['POST'], requirements: ['index' => '\d+'])]
    public function check(Request $request, Task $task, int $index): JsonResponse
    {
        $this->checkToken($request);
        if (!$this->mechanic($request)) {
            return new JsonResponse(['error' => 'Scegli di nuovo il tuo nome'], 403);
        }
        try {
            $done = $task->toggleCheck($index);
        } catch (\OutOfRangeException) {
            return new JsonResponse(['error' => 'Controllo non trovato'], 404);
        }
        $this->em->flush();

        return new JsonResponse(['done' => $done, 'count' => $task->getChecklistDone(), 'total' => count($task->getChecklist())]);
    }

    #[Route('/ricambio/{id}/qty', name: 'tablet_part_qty', methods: ['POST'])]
    public function partQuantity(Request $request, Part $part): JsonResponse
    {
        $this->checkToken($request);
        if (!$this->mechanic($request)) {
            return new JsonResponse(['error' => 'Scegli di nuovo il tuo nome'], 403);
        }
        $part->setQuantity($part->getQuantity() + ($request->request->getInt('delta') < 0 ? -1 : 1));
        $this->em->flush();

        return new JsonResponse(['qty' => $part->getQuantity()]);
    }

    /** Ricambio aggiunto dal meccanico: preso a magazzino o da far ordinare all'ufficio */
    #[Route('/lavoro/{id}/part', name: 'tablet_part_add', methods: ['POST'])]
    public function addPart(Request $request, Task $task, ActivityLogger $logger): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $description = trim((string) $request->request->get('description'));
        if ($description !== '') {
            $missing = $request->request->getBoolean('missing');
            $part = (new Part())->setDescription($description)->setCode(trim((string) $request->request->get('code')) ?: null)
                ->setQuantity(max(1, (float) str_replace(',', '.', (string) $request->request->get('quantity', '1'))))
                ->setUnit((string) $request->request->get('unit', 'pz'))
                ->setStatus($missing ? Part::TO_ORDER : Part::STOCK)
                ->setTask($task)->setAddedBy($me);
            $task->getWorkOrder()->addPart($part);
            $this->em->persist($part);
            $logger->order($task->getWorkOrder(), ($missing ? 'Ricambio da ordinare: ' : 'Ricambio usato: ').$description, $this->user(), $me);
            $this->em->flush();
            $this->addFlash('success', $missing ? 'Richiesta passata all\'ufficio' : 'Ricambio registrato');
        }

        return $this->redirectToRoute('tablet_task', ['id' => $task->getId(), 'tab' => 'parts']);
    }

    #[Route('/lavoro/{id}/note', name: 'tablet_note', methods: ['POST'])]
    public function note(Request $request, Task $task): Response
    {
        $this->checkToken($request);
        if (!$this->mechanic($request)) {
            return $this->toChoice();
        }
        $task->setNotes(trim((string) $request->request->get('notes')) ?: null);
        $km = (int) preg_replace('/\D/', '', (string) $request->request->get('km'));
        if ($km > 0) {
            $task->getWorkOrder()->readKm($km);
        }
        $this->em->flush();
        $this->addFlash('success', 'Salvato');

        return $this->redirectToRoute('tablet_task', ['id' => $task->getId(), 'tab' => 'note']);
    }

    #[Route('/lavoro/{id}/photo', name: 'tablet_photo', methods: ['POST'])]
    public function photo(Request $request, Task $task, AttachmentStorage $storage): Response
    {
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $this->storeUploads($request, $storage, $this->em, fn ($a) => $a->setWorkOrder($task->getWorkOrder())->setTask($task)->setMechanic($me));

        return $this->redirectToRoute('tablet_task', ['id' => $task->getId(), 'tab' => 'note']);
    }

    /**
     * Segnalazione all'ufficio: lavoro extra da autorizzare, problema di sicurezza o semplice nota.
     * Le prime due diventano una lavorazione "Da autorizzare" nell'ordine.
     */
    #[Route('/lavoro/{id}/report', name: 'tablet_report', methods: ['POST'])]
    public function report(Request $request, Task $task, ActivityLogger $logger, AttachmentStorage $storage): Response
    {
        $this->checkToken($request);
        if (!$me = $this->mechanic($request)) {
            return $this->toChoice();
        }
        $text = trim((string) $request->request->get('text'));
        $kind = (string) $request->request->get('kind');
        if ($text === '') {
            $this->addFlash('danger', 'Scrivi cosa hai trovato');

            return $this->redirectToRoute('tablet_task', ['id' => $task->getId()]);
        }
        $order = $task->getWorkOrder();
        if ($kind === 'info') {
            $logger->order($order, 'Nota da '.$me->getName().': '.$text, $this->user(), $me);
        } else {
            $minutes = max(0, $request->request->getInt('minutes'));
            $extra = (new Task())->setTitle(mb_substr($text, 0, 150))->setDescription(mb_strlen($text) > 150 ? $text : null)
                ->setEstimatedMinutes($minutes ?: 30)->setUrgent($kind === 'urgent');
            $this->workflow->propose($order, $extra, $me, $this->user());
            $this->em->persist($extra);
        }
        foreach ($request->files->all('files') as $file) {
            if ($file) {
                try {
                    $storage->store($file, $this->user())->setWorkOrder($order)->setTask($task)->setMechanic($me);
                } catch (\DomainException $e) {
                    $this->addFlash('danger', $e->getMessage());
                }
            }
        }
        $this->em->flush();
        $this->addFlash('success', $kind === 'info' ? 'Nota passata all\'ufficio' : 'Segnalazione inviata: l\'ufficio sente il cliente');

        return $this->redirectToRoute('tablet_task', ['id' => $task->getId(), 'tab' => 'note']);
    }
}
