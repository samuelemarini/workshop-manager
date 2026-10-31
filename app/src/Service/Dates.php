<?php

namespace App\Service;

use App\Util\Trans;

/**
 * Date in forma parlata, come le dice l'officina: "oggi 15:30", "domani", "lun 26/10" (nella lingua attiva).
 */
final class Dates
{
    /** Data col pattern ICU nella lingua attiva: "EEE" = "lun", "EEEE d MMMM y" = "giovedì 22 ottobre 2026". */
    public static function format(\DateTimeInterface $date, string $pattern): string
    {
        $formatter = new \IntlDateFormatter(Language::current(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $date->getTimezone(), null, $pattern);

        return (string) $formatter->format($date);
    }

    /** Data con l'ordine e la punteggiatura della lingua: "EEEEdMMMMy" = "giovedì 29 ottobre 2026", "Donnerstag, 29. Oktober 2026". */
    public static function skeleton(\DateTimeInterface $date, string $skeleton): string
    {
        $pattern = (new \IntlDatePatternGenerator(Language::current()))->getBestPattern($skeleton);

        return self::format($date, $pattern ?: $skeleton);
    }

    public static function when(?\DateTimeInterface $date, bool $withTime = true): string
    {
        if (!$date) {
            return '';
        }
        $today = new \DateTimeImmutable('today');
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        $diff = (int) $today->diff($day)->format('%r%a');
        $time = $withTime && $date->format('H:i') !== '00:00' ? ' '.$date->format('G:i') : '';

        return match ($diff) {
            0 => Trans::t('oggi').$time,
            1 => Trans::t('domani').$time,
            -1 => Trans::t('ieri').$time,
            default => mb_strtolower(rtrim(self::format($date, 'EEE'), '.')).' '.$date->format('d/m').$time,
        };
    }
}
