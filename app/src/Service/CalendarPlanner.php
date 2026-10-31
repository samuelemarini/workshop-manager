<?php

namespace App\Service;

use App\Entity\Task;
use App\Repository\MechanicRepository;
use App\Repository\TaskRepository;
use App\Repository\WorkOrderRepository;

/**
 * Lavori distribuiti sui giorni, per la vista settimana e mese del planning.
 *
 * Una lavorazione compare nei giorni in cui è stata lavorata (con le ore registrate quel giorno)
 * e, se non è ancora finita, nel giorno pianificato con il tempo che resta da fare.
 */
class CalendarPlanner
{
    public function __construct(
        private TaskRepository $tasks,
        private MechanicRepository $mechanics,
        private WorkOrderRepository $orders,
    ) {
    }

    /**
     * @return array{mechanics: array, days: array<string, array{items: array, load: array<int|string, int>, deliveries: array}>}
     */
    public function range(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $now = new \DateTimeImmutable();
        $days = [];
        for ($d = $from; $d < $to; $d = $d->modify('+1 day')) {
            $days[$d->format('Y-m-d')] = ['date' => $d, 'items' => [], 'load' => [], 'deliveries' => []];
        }

        foreach ($this->tasks->findInRange($from, $to) as $task) {
            $perDay = [];
            foreach ($task->getTimeEntries() as $e) {
                $start = \DateTimeImmutable::createFromInterface($e->getStartedAt());
                $end = \DateTimeImmutable::createFromInterface($e->getEndedAt() ?? $now);
                for ($d = $start->setTime(0, 0); $d < $end; $d = $d->modify('+1 day')) {
                    $key = $d->format('Y-m-d');
                    if (!isset($days[$key])) {
                        continue;
                    }
                    $seconds = min($end->getTimestamp(), $d->modify('+1 day')->getTimestamp()) - max($start->getTimestamp(), $d->getTimestamp());
                    $perDay[$key]['minutes'] = ($perDay[$key]['minutes'] ?? 0) + intdiv(max(0, $seconds), 60);
                    $perDay[$key]['time'] ??= $start > $d ? $start->format('G:i') : null;
                }
            }
            $open = in_array($task->getStatus(), [Task::TODO, Task::PAUSED, Task::PROPOSED, Task::RUNNING], true);
            if ($open && $task->getPlannedStart()) {
                $key = $task->getPlannedStart()->format('Y-m-d');
                if (isset($days[$key]) && !isset($perDay[$key])) {
                    $perDay[$key] = [
                        'minutes' => max(15, $task->getEstimatedMinutes() - intdiv($task->getWorkedSeconds($now), 60)),
                        'time' => $task->getPlannedStart()->format('G:i'),
                        'planned' => true,
                    ];
                }
            }
            foreach ($perDay as $key => $info) {
                $mechanicKey = $task->getMechanic()?->getId() ?? 0;
                $days[$key]['items'][] = [
                    'task' => $task,
                    'mechanicKey' => $mechanicKey,
                    'minutes' => $info['minutes'],
                    'time' => $info['time'],
                    'kind' => $this->kind($task, $info['planned'] ?? false),
                ];
                if ($task->counts()) {
                    $days[$key]['load'][$mechanicKey] = ($days[$key]['load'][$mechanicKey] ?? 0) + $info['minutes'];
                }
            }
        }

        foreach ($days as &$day) {
            usort($day['items'], fn ($a, $b) => [$a['time'] === null ? 1 : 0, str_pad((string) $a['time'], 5, '0', STR_PAD_LEFT)] <=> [$b['time'] === null ? 1 : 0, str_pad((string) $b['time'], 5, '0', STR_PAD_LEFT)]);
        }
        unset($day);

        foreach ($this->orders->findPromisedBetween($from, $to) as $order) {
            $days[$order->getPromisedAt()->format('Y-m-d')]['deliveries'][] = $order;
        }

        return ['mechanics' => $this->mechanics->findActive(), 'days' => $days];
    }

    private function kind(Task $task, bool $planned): string
    {
        return match ($task->getStatus()) {
            Task::RUNNING => 'run',
            Task::DONE => 'done',
            Task::PROPOSED => 'tbc',
            Task::PAUSED => $planned ? 'plan' : (in_array($task->getRawPauseReason(), Task::WAITING_REASONS, true) ? 'block' : 'pause'),
            default => 'plan',
        };
    }
}
