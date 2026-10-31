<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use App\Util\Trans;
use Doctrine\ORM\Mapping as ORM;

/**
 * Storico delle modifiche e dei cambi di stato degli ordini di lavoro.
 *
 * Il messaggio si salva in italiano (da uno dei modelli di MESSAGES, coi parametri sostituiti) e si
 * traduce quando si mostra: getMessage() riconosce il modello e lo traduce nella lingua di chi legge,
 * lasciando com'è i dati (titoli, descrizioni, nomi). Vale anche per le righe già salvate.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(fields: ['subjectType', 'subjectId'])]
class ActivityLog
{
    public const SUBJECT_ORDER = 'ORDER';

    /** Modelli dei messaggi (chiavi di traduzione), dal più specifico al più generico */
    public const MESSAGES = [
        'Avviata: %task%',
        'Ripresa: %task%',
        'In pausa (%reason%): %task%',
        'Completata: %task%',
        'Riaperta: %task%',
        'Problema di sicurezza segnalato: %task%',
        'Lavoro extra proposto: %task%',
        'OK del cliente: %task%',
        'Rifiutata dal cliente: %task%',
        'Lavorazioni completate: si aspetta l\'OK del cliente sugli extra',
        'Tutte le lavorazioni completate: auto pronta',
        'Stato: %status% (%reason%)',
        'Stato: %status%',
        'Lavorazione aggiunta: %task%',
        'Lavorazione eliminata: %task%',
        'Accettazione: %plate% di %customer%',
        'Dati dell\'ordine modificati',
        'Cliente avvisato (%via%)',
        'Cliente avvisato',
        'Pianificata: %task%',
        'Presa in carico dal tablet: %task%',
        'Ricambio da ordinare: %part%',
        'Ricambio usato: %part%',
        'Ricambio aggiunto: %part%',
        'Nota da %mechanic%: %text%',
    ];

    // parametri che sono etichette fisse (motivi di pausa, stati, canali di contatto): si traducono anche loro
    private const LABEL_PARAMS = ['%reason%', '%status%', '%via%'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    #[ORM\Column(length: 255)]
    private string $message;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    // azioni dal tablet: il meccanico che ha toccato il pulsante
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Mechanic $mechanic = null;

    public function __construct(string $subjectType, int $subjectId, string $message, ?User $user, ?Mechanic $mechanic = null)
    {
        $this->mechanic = $mechanic;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->message = mb_substr($message, 0, 255);
        $this->user = $user;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubjectType(): string { return $this->subjectType; }
    public function getSubjectId(): int { return $this->subjectId; }
    /** Messaggio tradotto nella lingua di chi legge */
    public function getMessage(): string
    {
        foreach (self::MESSAGES as $template) {
            $params = self::match($template, $this->message);
            if ($params !== null) {
                foreach ($params as $name => $value) {
                    if (in_array($name, self::LABEL_PARAMS, true)) {
                        // "Attesa ricambi · volano lunedì": si traduce il motivo, il resto è testo libero
                        $pieces = explode(' · ', $value, 2);
                        $pieces[0] = Trans::label($pieces[0]);
                        $params[$name] = implode(' · ', $pieces);
                    }
                }

                return Trans::t($template, $params);
            }
        }

        return $this->message;
    }

    /** Il testo salvato (italiano) */
    public function getRawMessage(): string { return $this->message; }

    /** @return array<string, string>|null parametri del modello, se il messaggio lo segue */
    private static function match(string $template, string $message): ?array
    {
        static $regexes = [];
        if (!isset($regexes[$template])) {
            $pieces = preg_split('/(%[a-z]+%)/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
            $names = [];
            $regex = '';
            foreach ($pieces as $i => $piece) {
                if ($i % 2) {
                    $names[] = $piece;
                    $regex .= '(.+?)';
                } else {
                    $regex .= preg_quote($piece, '/');
                }
            }
            $regexes[$template] = ['/^'.$regex.'$/su', $names];
        }
        [$regex, $names] = $regexes[$template];
        if (!preg_match($regex, $message, $m)) {
            return null;
        }

        return $names ? array_combine($names, array_slice($m, 1)) : [];
    }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUser(): ?User { return $this->user; }
    public function getMechanic(): ?Mechanic { return $this->mechanic; }

    public function getAuthorName(): ?string
    {
        return $this->mechanic?->getName() ?? $this->user?->getFullName();
    }
}
