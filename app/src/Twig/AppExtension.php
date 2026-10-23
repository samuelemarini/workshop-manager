<?php

namespace App\Twig;

use App\Repository\TaskRepository;
use App\Service\Dates;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private const DAYS = ['', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    private const MONTHS = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    private ?int $proposed = null;

    public function __construct(private TaskRepository $tasks)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('proposed_count', [$this, 'proposedCount']),
        ];
    }

    /** Lavori extra segnalati dal tablet in attesa dell'OK del cliente: badge nel menu */
    public function proposedCount(): int
    {
        return $this->proposed ??= count($this->tasks->findProposed());
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('num', [$this, 'num']),
            new TwigFilter('dow', [$this, 'dayOfWeek']),
            new TwigFilter('long_date', [$this, 'longDate']),
            new TwigFilter('money', [$this, 'money']),
            new TwigFilter('duration', [$this, 'duration']),
            new TwigFilter('minutes', [$this, 'minutes']),
            new TwigFilter('hours', [$this, 'hours']),
            new TwigFilter('when', [$this, 'when']),
        ];
    }

    /** 9742 → "9.742"; 2.50 → "2,5" */
    public function num(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = (float) $value;
        if ($decimals === 0 && floor($value) != $value) {
            $decimals = 1;
        }

        return number_format($value, $decimals, ',', '.');
    }

    public function dayOfWeek(\DateTimeInterface $date): string
    {
        return self::DAYS[(int) $date->format('N')];
    }

    /** "giovedì 22 ottobre 2026" */
    public function longDate(\DateTimeInterface $date): string
    {
        $days = ['', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];

        return $days[(int) $date->format('N')].' '.$date->format('j').' '.self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');
    }

    /** "€ 258,20" */
    public function money(int|float|string|null $value): string
    {
        return $value === null || $value === '' ? '' : '€ '.number_format((float) $value, 2, ',', '.');
    }

    /** Secondi → "1:40" (ore:minuti) */
    public function duration(?int $seconds): string
    {
        $minutes = intdiv(max(0, (int) $seconds), 60);

        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Minuti → "1:30" */
    public function minutes(?int $minutes): string
    {
        return $this->duration((int) $minutes * 60);
    }

    /** Secondi → "2,4 h" */
    public function hours(?int $seconds): string
    {
        return number_format(((int) $seconds) / 3600, 1, ',', '.').' h';
    }

    /** "oggi 15:30", "domani 9:00", "lun 26/10" */
    public function when(?\DateTimeInterface $date, bool $withTime = true): string
    {
        return Dates::when($date, $withTime);
    }
}
