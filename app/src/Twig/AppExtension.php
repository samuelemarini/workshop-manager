<?php

namespace App\Twig;

use App\Repository\TaskRepository;
use App\Service\Dates;
use App\Service\Language;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private ?int $proposed = null;

    public function __construct(private TaskRepository $tasks)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('proposed_count', [$this, 'proposedCount']),
            // [decimali, migliaia] della lingua attiva, per <html data-decimal data-group>
            new TwigFunction('number_separators', [Language::class, 'separators']),
            new TwigFunction('weekday_names', [$this, 'weekdayNames']),
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
            new TwigFilter('local_date', [Dates::class, 'format']),
            new TwigFilter('date_skeleton', [Dates::class, 'skeleton']),
            new TwigFilter('money', [$this, 'money']),
            new TwigFilter('duration', [$this, 'duration']),
            new TwigFilter('minutes', [$this, 'minutes']),
            new TwigFilter('hours', [$this, 'hours']),
            new TwigFilter('when', [$this, 'when']),
        ];
    }

    /** 9742 → "9.742"; 2.50 → "2,5" (separatori della lingua attiva) */
    public function num(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = (float) $value;
        if ($decimals === 0 && floor($value) != $value) {
            $decimals = 1;
        }

        [$decimal, $group] = Language::separators();

        return number_format($value, $decimals, $decimal, $group);
    }

    /** "Lun", "Mon", "Lun."... */
    public function dayOfWeek(\DateTimeInterface $date): string
    {
        return mb_convert_case(rtrim(Dates::format($date, 'EEE'), '.'), MB_CASE_TITLE);
    }

    /** Nomi brevi dei giorni da lunedì a domenica nella lingua attiva: ["Lun", "Mar", ...] */
    public function weekdayNames(): array
    {
        $monday = new \DateTimeImmutable('monday this week');

        return array_map(fn (int $i) => $this->dayOfWeek($monday->modify("+$i days")), range(0, 6));
    }

    /** "giovedì 22 ottobre 2026", "Thursday 22 October 2026" */
    public function longDate(\DateTimeInterface $date): string
    {
        return Dates::skeleton($date, 'EEEEdMMMMy');
    }

    /** "€ 258,20" */
    public function money(int|float|string|null $value): string
    {
        return $value === null || $value === '' ? '' : '€ '.$this->num((float) $value, 2);
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
        return $this->num(((int) $seconds) / 3600, 1).' h';
    }

    /** "oggi 15:30", "domani 9:00", "lun 26/10" */
    public function when(?\DateTimeInterface $date, bool $withTime = true): string
    {
        return Dates::when($date, $withTime);
    }
}
