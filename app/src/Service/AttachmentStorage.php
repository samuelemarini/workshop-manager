<?php

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Salva i file caricati fuori dalla public dir. Restituisce l'Attachment già persistito
 * (senza flush): il chiamante lo collega a mezzo / lavorazione / intervento.
 */
class AttachmentStorage
{
    public const MAX_SIZE = 30 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private string $uploadDir,
    ) {
    }

    public function store(UploadedFile $file, ?User $user): Attachment
    {
        if (!$file->isValid()) {
            throw new \DomainException('Caricamento non riuscito: '.$file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \DomainException('Il file supera la dimensione massima di 30 MB.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $storedName = bin2hex(random_bytes(16)).'.'.preg_replace('/[^a-z0-9]/', '', $extension);
        $size = (int) $file->getSize();
        $mime = $file->getMimeType();

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
        $file->move($this->uploadDir, $storedName);

        $attachment = (new Attachment($file->getClientOriginalName(), $storedName))
            ->setMimeType($mime)
            ->setSize($size)
            ->setUploadedBy($user);
        $this->em->persist($attachment);

        return $attachment;
    }

    public function path(Attachment $attachment): string
    {
        return $this->uploadDir.'/'.$attachment->getStoredName();
    }

    public function delete(Attachment $attachment): void
    {
        $path = $this->path($attachment);
        if (is_file($path)) {
            unlink($path);
        }
        $this->em->remove($attachment);
    }
}
