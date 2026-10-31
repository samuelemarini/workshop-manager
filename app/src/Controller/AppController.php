<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\User;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Base dei controller: utente tipizzato e verifica CSRF per le azioni POST senza form Symfony.
 */
abstract class AppController extends AbstractController
{
    public static function getSubscribedServices(): array
    {
        return parent::getSubscribedServices() + ['translator' => TranslatorInterface::class];
    }

    /** Testo per l'utente nella sua lingua: la frase italiana è la chiave (dominio messages). */
    protected function trans(string $id, array $params = []): string
    {
        return $this->container->get('translator')->trans($id, $params);
    }

    protected function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** Basta uno dei ruoli indicati (es. un fermo macchina lo segnala l'officina o l'ufficio tecnico) */
    protected function denyUnlessAny(string ...$roles): void
    {
        foreach ($roles as $role) {
            if ($this->isGranted($role)) {
                return;
            }
        }
        throw $this->createAccessDeniedException();
    }

    /** Le azioni a pulsante usano lo stesso token stateless "submit" dei form. */
    protected function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }
    }

    protected function back(Request $request, string $fallbackRoute, array $params = []): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $referer = $request->headers->get('referer');

        return $referer ? $this->redirect($referer) : $this->redirectToRoute($fallbackRoute, $params);
    }

    /**
     * Upload multiplo (campo files[]): ogni file viene salvato e passato a $link per collegarlo
     * al mezzo / lavorazione / intervento.
     *
     * @param callable(Attachment): void $link
     */
    protected function storeUploads(Request $request, AttachmentStorage $storage, EntityManagerInterface $em, callable $link): void
    {
        $this->checkToken($request);
        $count = 0;
        try {
            foreach ($request->files->all('files') as $file) {
                if ($file) {
                    $link($storage->store($file, $this->user()));
                    $count++;
                }
            }
            $em->flush();
            $this->addFlash('success', $this->trans('Documento caricato|%count% documenti caricati', ['%count%' => $count]));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
    }

    /** Come dateParam(), ma mutabile: è il tipo atteso dai campi data delle entità. */
    protected function entityDate(?string $value, string $default): \DateTime
    {
        return \DateTime::createFromImmutable($this->dateParam($value, $default));
    }

    /** Data "Y-m-d" da query/post, oppure il default. */
    protected function dateParam(?string $value, string $default): \DateTimeImmutable
    {
        $date = $value ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date ?: new \DateTimeImmutable($default);
    }
}
