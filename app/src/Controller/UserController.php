<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AppController
{
    #[Route('/users', name: 'user_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(UserRepository $users): Response
    {
        return $this->render('user/index.html.twig', ['users' => $users->findBy(['cancel' => 0], ['surname' => 'ASC', 'name' => 'ASC'])]);
    }

    #[Route('/users/new', name: 'user_new')]
    #[Route('/users/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, EntityManagerInterface $em, UserRepository $users, UserPasswordHasherInterface $hasher, ?User $user = null): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        // un utente non amministratore può modificare solo il proprio profilo
        if (!$isAdmin && (!$user || $user->getId() !== $this->user()->getId())) {
            throw $this->createAccessDeniedException();
        }
        if ($user && $user->getCancel()) {
            throw $this->createNotFoundException();
        }

        $user ??= (new User())->setCreatedBy($this->user());
        $form = $this->createForm(UserType::class, $user, ['admin' => $isAdmin]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $duplicate = $users->findOneBy(['username' => $user->getUsername(), 'cancel' => 0]);
            if ($duplicate && $duplicate !== $user) {
                $form->get('username')->addError(new FormError($this->trans('Username già presente nel sistema')));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user->getPlainPassword()) {
                $user->setPassword($hasher->hashPassword($user, $user->getPlainPassword()));
            }
            $user->setUpdatedAt(new \DateTime())->setUpdatedBy($this->user());
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', $this->trans('Utente salvato'));

            return $this->redirectToRoute($isAdmin ? 'user_index' : 'homepage');
        }

        return $this->render('user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        if ($user === $this->user()) {
            $this->addFlash('danger', $this->trans('Non puoi eliminare il tuo utente.'));

            return $this->redirectToRoute('user_edit', ['id' => $user->getId()]);
        }
        // cancellazione logica: lo username viene liberato per un eventuale nuovo utente
        $user->setUsername($user->getUsername().'#'.time())->setCancel(1)->setUpdatedBy($this->user())->setUpdatedAt(new \DateTime());
        $em->flush();
        $this->addFlash('success', $this->trans('Utente eliminato'));

        return $this->redirectToRoute('user_index');
    }
}
