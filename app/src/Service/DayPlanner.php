<?php

namespace App\Service;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Repository\MechanicRepository;
use App\Repository\SettingRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;

/**
 * Griglia del planning di un giorno: una riga per meccanico, colonne da 15 minuti
 * fra apertura e chiusura dell'officina.
 *
 * Dove sta una barra:
 * - lavorazione già toccata quel giorno (registrazioni tempi): dal primo avvio; finisce all'ultima
 *   registrazione se completata, altrimenti dopo il tempo stimato o il lavorato, il maggiore dei due;
 * - altrimenti all'orario pianificato, lunga quanto il tempo stimato.
 */
class DayPlanner
{
    public const SLOT = 15;

    public function __construct(
        private TaskRepository $tasks,
        private MechanicRepository $mechanics,
        private TimeEntryRepository $entries,
        private SettingRepository $settings,
    ) {
    }

    /**
     * @return array{start: int, end: int, slots: int, hours: array, lunch: ?array, now: ?array, rows: array, loads: array}
     */
    public function day(\DateTimeImmutable $day): array
    {
        $start = $this->settings->minutes('day_start');
        $end = max($start + 60, $this->settings->minutes('day_end'));
        $slots = intdiv($end - $start, self::SLOT);
        $col = fn (int $minute) => intdiv(max(0, min($end, $minute) - $start), self::SLOT) + 2;
        $now = new \DateTimeImmutable();
        $isToday = $day->format('Y-m-d') === $now->format('Y-m-d');

        $hours = [];
        for ($m = $start - $start % 60 + ($start % 60 ? 60 : 0); $m < $end; $m += 60) {
            $hours[] = ['label' => intdiv($m, 60).':00', 'col' => $col($m), 'span' => min(60, $end - $m) / self::SLOT];
        }
        $lunchStart = $this->settings->minutes('lunch_start');
        $lunchEnd = $this->settings->minutes('lunch_end');
        $lunch = $lunchEnd > $lunchStart ? ['col' => $col($lunchStart), 'span' => intdiv($lunchEnd - $lunchStart, self::SLOT)] : null;

        $nowMarker = null;
        $nowMinute = (int) $now->format('G') * 60 + (int) $now->format('i');
        if ($isToday && $nowMinute >= $start && $nowMinute < $end) {
            $nowMarker = ['col' => $col($nowMinute), 'offset' => round(($nowMinute - $start) % self::SLOT / self::SLOT * 100), 'label' => $now->format('G:i')];
        }

        // righe
        $rows = [];
        foreach ($this->mechanics->findActive() as $m) {
            $rows[$m->getId()] = $this->row('m'.$m->getId(), $m->getName(), null, $m->getInitials(), $m->getColor(), ['mechanic' => $m->getId()]);
        }

        $dayStart = $day->setTime(0, 0);
        $planned = [];
        foreach ($this->tasks->findForDay($day) as $task) {
            [$from, $to] = $this->span($task, $dayStart, $now);
            if ($from === null || $to <= $start || $from >= $end) {
                continue;
            }
            $key = $task->getMechanic()?->getId();
            if ($key === null || !isset($rows[$key])) {
                $key = 0;
                $rows[0] ??= $this->row('none', 'Da assegnare', null, '?', 'none', ['mechanic' => '']);
            }
            $rows[$key]['bars'][] = $this->bar($task, $from, $to, $col, $now);
            if ($task->getMechanic() && $task->counts()) {
                $planned[$task->getMechanic()->getId()] = ($planned[$task->getMechanic()->getId()] ?? 0) + ($to - $from);
            }
        }

        foreach ($rows as &$row) {
            $this->assignLanes($row);
        }
        unset($row);

        // spazi liberi di almeno un'ora per ogni meccanico (solo oggi e giorni futuri)
        if ($day >= $now->setTime(0, 0)) {
            $from = $isToday ? max($start, (int) (ceil($nowMinute / self::SLOT) * self::SLOT)) : $start;
            foreach ($rows as $id => &$row) {
                if ($id === 0) {
                    continue;
                }
                foreach ($this->freeWindows($row['bars'], $from, $end, [$lunchStart, $lunchEnd]) as [$a, $b]) {
                    $row['free'][] = ['gc' => $col($a).' / span '.intdiv($b - $a, self::SLOT), 'label' => 'Libero '.$this->hm($b - $a), 'start' => $this->clock($a), 'range' => $this->clock($a).'–'.$this->clock($b), 'from' => $a];
                }
            }
            unset($row);
        }

        // carico di ogni meccanico
        $worked = $this->entries->secondsByMechanic($dayStart, $dayStart->modify('+1 day'));
        $loads = [];
        foreach ($this->mechanics->findActive() as $m) {
            $capacity = (int) round($m->getDailyHours() * 60);
            $plan = $planned[$m->getId()] ?? 0;
            $loads[] = [
                'mechanic' => $m,
                'planned' => $plan,
                'capacity' => $capacity,
                'worked' => $worked[$m->getId()] ?? 0,
                'pct' => $capacity ? min(100, (int) round($plan / $capacity * 100)) : 0,
                'workedPct' => $capacity ? min(100, (int) round(($worked[$m->getId()] ?? 0) / 60 / $capacity * 100)) : 0,
            ];
        }

        return [
            'start' => $start,
            'end' => $end,
            'slots' => $slots,
            'hours' => $hours,
            'lunch' => $lunch,
            'now' => $nowMarker,
            'rows' => array_values($rows),
            'loads' => $loads,
        ];
    }

    private function row(string $key, string $name, ?string $sub, string $initials, string $color, array $target): array
    {
        return ['key' => $key, 'name' => $name, 'sub' => $sub, 'initials' => $initials, 'color' => $color, 'target' => $target, 'bars' => [], 'free' => [], 'lanes' => 1];
    }

    /** @return array{0: ?int, 1: int} minuti dalla mezzanotte */
    private function span(Task $task, \DateTimeImmutable $dayStart, \DateTimeImmutable $now): array
    {
        $minute = fn (\DateTimeInterface $d) => (int) floor(($d->getTimestamp() - $dayStart->getTimestamp()) / 60);
        $dayEnd = $dayStart->modify('+1 day');

        $entries = array_filter($task->getTimeEntries()->toArray(), fn ($e) => $e->getStartedAt() < $dayEnd && ($e->getEndedAt() ?? $now) > $dayStart);
        if ($entries) {
            $first = reset($entries)->getStartedAt();
            $from = max(0, $minute($first));
            if ($task->getStatus() === Task::DONE) {
                $last = end($entries);
                $to = $minute($last->getEndedAt() ?? $now);
            } else {
                $to = $from + max($task->getEstimatedMinutes(), intdiv($task->getWorkedSeconds($now), 60), self::SLOT);
            }

            return [$from, max($to, $from + self::SLOT)];
        }
        if ($task->getPlannedStart()) {
            $from = $minute($task->getPlannedStart());

            return [$from, $from + max(self::SLOT, $task->getEstimatedMinutes())];
        }

        return [null, 0];
    }

    private function bar(Task $task, int $from, int $to, callable $col, \DateTimeImmutable $now): array
    {
        $kind = match ($task->getStatus()) {
            Task::RUNNING => 'run',
            Task::DONE => 'done',
            Task::PROPOSED => 'tbc',
            Task::PAUSED => in_array($task->getPauseReason(), Task::WAITING_REASONS, true) ? 'block' : 'pause',
            default => 'plan',
        };
        $order = $task->getWorkOrder();
        $head = $order->getCode();
        if ($kind === 'run') {
            $head .= ' · '.$this->hm(intdiv($task->getWorkedSeconds($now), 60));
        }
        $text = $task->getTitle().' · '.$order->getVehicle()->getName();
        if ($kind === 'block' || $kind === 'pause') {
            $text = $task->getPauseReason().' · '.$text;
        }
        return [
            'task' => $task,
            'kind' => $kind,
            'from' => $from,
            'to' => $to,
            'c1' => $col($from),
            'c2' => max($col($from) + 1, $col($to)),
            'head' => $head,
            'text' => $text,
            'range' => $this->clock($from).'–'.$this->clock($to),
            'title' => $this->clock($from).'–'.$this->clock($to).' · '.$task->getTitle().' · '.$order->getVehicle()->getPlateLabel().' · '.$task->getStatusLabel(),
            'draggable' => in_array($task->getStatus(), [Task::TODO, Task::PAUSED], true),
            'lane' => 1,
        ];
    }

    /** Barre sovrapposte nella stessa riga: vanno su corsie diverse */
    private function assignLanes(array &$row): void
    {
        usort($row['bars'], fn ($a, $b) => [$a['from'], $a['to']] <=> [$b['from'], $b['to']]);
        $laneEnds = [];
        foreach ($row['bars'] as &$bar) {
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $bar['from']) {
                $lane++;
            }
            $laneEnds[$lane] = $bar['to'];
            $bar['lane'] = $lane + 1;
            $bar['gc'] = $bar['c1'].' / '.$bar['c2'];
        }
        unset($bar);
        $row['lanes'] = max(1, count($laneEnds));
    }

    /** @return array<int, array{0: int, 1: int}> intervalli liberi di almeno 60 minuti */
    private function freeWindows(array $bars, int $from, int $end, array $lunch): array
    {
        $busy = array_map(fn ($b) => [$b['from'], $b['to']], array_filter($bars, fn ($b) => $b['kind'] !== 'tbc'));
        if ($lunch[1] > $lunch[0]) {
            $busy[] = $lunch;
        }
        usort($busy, fn ($a, $b) => $a[0] <=> $b[0]);
        $free = [];
        $cursor = $from;
        foreach ($busy as [$a, $b]) {
            if ($a - $cursor >= 60) {
                $free[] = [$cursor, $a];
            }
            $cursor = max($cursor, $b);
        }
        if ($end - $cursor >= 60) {
            $free[] = [$cursor, $end];
        }

        return $free;
    }

    private function clock(int $minute): string
    {
        return intdiv($minute, 60).':'.str_pad((string) ($minute % 60), 2, '0', STR_PAD_LEFT);
    }

    private function hm(int $minutes): string
    {
        return $minutes % 60 ? intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' h' : intdiv($minutes, 60).' h';
    }
}
