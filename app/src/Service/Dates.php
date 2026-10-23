<?php

namespace App\Service;

/**
 * Date in forma parlata, come le dice l'officina: "oggi 15:30", "domani", "lun 26/10".
 */
final class Dates
{
    private const DAYS = ['', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];

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
            0 => 'oggi'.$time,
            1 => 'domani'.$time,
            -1 => 'ieri'.$time,
            default => self::DAYS[(int) $date->format('N')].' '.$date->format('d/m').$time,
        };
    }
}
