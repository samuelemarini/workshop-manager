<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/attachments')]
class AttachmentController extends AppController
{
    /** PDF e immagini si aprono nel browser, il resto si scarica. */
    #[Route('/{id}', name: 'attachment_view', requirements: ['id' => '\d+'])]
    public function view(Attachment $attachment, AttachmentStorage $storage): Response
    {
        $path = $storage->path($attachment);
        if (!is_file($path)) {
            throw $this->createNotFoundException('File non trovato');
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            $attachment->isPdf() || $attachment->isImage() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getOriginalName(),
            preg_replace('/[^\x20-\x7e]/', '_', $attachment->getOriginalName())
        );

        return $response;
    }

    #[Route('/{id}/delete', name: 'attachment_delete', methods: ['POST'])]
    public function delete(Request $request, Attachment $attachment, AttachmentStorage $storage, EntityManagerInterface $em): Response
    {
        $this->checkToken($request);
        // chi l'ha caricato o un amministratore
        if ($attachment->getUploadedBy() !== $this->user() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $storage->delete($attachment);
        $em->flush();
        $this->addFlash('success', 'Allegato eliminato');

        return $this->back($request, 'homepage');
    }
}
