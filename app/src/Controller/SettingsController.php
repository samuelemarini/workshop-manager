<?php

namespace App\Controller;

use App\Entity\TaskTemplate;
use App\Entity\Workstation;
use App\Form\TaskTemplateType;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Impostazioni dell'officina (solo amministratori): orari e tariffa, postazioni, lavorazioni tipo.
 * Postazioni e lavorazioni tipo usate non si cancellano: si disattivano.
 */
#[Route('/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AppController
{
    #[Route('', name: 'settings_index')]
    public function index(EntityManagerInterface $em, SettingRepository $settings): Response
    {
        return $this->render('settings/index.html.twig', [
            'settings' => $settings->all(),
            'workstations' => $em->getRepository(Workstation::class)->findBy([], ['cancel' => 'ASC', 'sort' => 'ASC', 'name' => 'ASC']),
            'templates' => $em->getRepository(TaskTemplate::class)->findBy([], ['cancel' => 'ASC', 'sort' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/workshop', name: 'settings_workshop', methods: ['POST'])]
    public function workshop(Request $request, SettingRepository $settings, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        foreach (array_keys(SettingRepository::DEFAULTS) as $name) {
            $value = trim((string) $request->request->get($name));
            $valid = $name === 'labour_rate' ? is_numeric(str_replace(',', '.', $value)) : preg_match('/^\d{1,2}:\d{2}$/', $value);
            if ($valid) {
                $settings->set($name, $value);
            }
        }
        $em->flush();
        $this->addFlash('success', 'Impostazioni salvate');

        return $this->redirectToRoute('settings_index');
    }

    #[Route('/workstation/save', name: 'settings_workstation_save', methods: ['POST'])]
    public function saveWorkstation(Request $request, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $name = trim((string) $request->request->get('name'));
        $id = $request->request->getInt('id');
        if ($name !== '') {
            $row = $id ? $em->find(Workstation::class, $id) : new Workstation();
            if (!$row) {
                throw $this->createNotFoundException();
            }
            $row->setName($name)->setDescription(trim((string) $request->request->get('description')) ?: null)->setSort($request->request->getInt('sort'));
            $em->persist($row);
            $em->flush();
        }

        return $this->redirectToRoute('settings_index');
    }

    #[Route('/workstation/{id}/toggle', name: 'settings_workstation_toggle', methods: ['POST'])]
    public function toggleWorkstation(Request $request, Workstation $workstation, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $workstation->setCancel($workstation->getCancel() ? 0 : 1);
        $em->flush();

        return $this->redirectToRoute('settings_index');
    }

    #[Route('/template/new', name: 'settings_template_new')]
    #[Route('/template/{id}/edit', name: 'settings_template_edit', requirements: ['id' => '\d+'])]
    public function editTemplate(Request $request, EntityManagerInterface $em, ?TaskTemplate $template = null): Response
    {
        $template ??= new TaskTemplate();
        $form = $this->createForm(TaskTemplateType::class, $template);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($template);
            $em->flush();
            $this->addFlash('success', 'Lavorazione tipo salvata');

            return $this->redirectToRoute('settings_index');
        }

        return $this->render('settings/_template_form.html.twig', ['form' => $form, 'template' => $template]);
    }

    #[Route('/template/{id}/toggle', name: 'settings_template_toggle', methods: ['POST'])]
    public function toggleTemplate(Request $request, TaskTemplate $template, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        $template->setCancel($template->getCancel() ? 0 : 1);
        $em->flush();

        return $this->redirectToRoute('settings_index');
    }
}
