<?php

namespace App\Command;

use App\Entity\ActivityLog;
use App\Entity\Attachment;
use App\Entity\Customer;
use App\Entity\Mechanic;
use App\Entity\Part;
use App\Entity\Role;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use App\Repository\WorkOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dati dimostrativi come nel mockup: una mattina in officina con Antonio e Riccardo al lavoro.
 * Gli orari sono relativi a adesso (lavorazioni in corso avviate un'ora fa, ecc.).
 * Con --reset cancella ordini, veicoli e clienti esistenti: mai in produzione.
 */
#[AsCommand(name: 'app:demo', description: 'Carica dati dimostrativi (ordini, veicoli, clienti, tempi)')]
class DemoCommand
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $today;
    /** @var array<string, Mechanic> */
    private array $mech = [];
    /** @var array<string, TaskTemplate> */
    private array $tpl = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private WorkOrderRepository $orders,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Cancella prima ordini, veicoli e clienti esistenti')] bool $reset = false,
    ): int
    {
        if ($this->orders->count([]) > 0 && !$reset) {
            $io->error('Ci sono già ordini di lavoro: usa --reset per sostituirli con i dati dimostrativi.');

            return 1;
        }
        if ($reset) {
            foreach ([Attachment::class, ActivityLog::class, WorkOrder::class, Vehicle::class, Customer::class] as $class) {
                $this->em->createQuery("DELETE FROM $class x")->execute();
            }
        }

        $this->now = new \DateTimeImmutable();
        $this->today = $this->now->setTime(0, 0);
        foreach ($this->em->getRepository(Mechanic::class)->findBy(['cancel' => 0], ['sort' => 'ASC']) as $m) {
            $this->mech[$m->getName()] = $m;
        }
        foreach ($this->em->getRepository(TaskTemplate::class)->findAll() as $t) {
            $this->tpl[$t->getName()] = $t;
        }
        if (!isset($this->mech['Antonio'], $this->mech['Riccardo'])) {
            $io->error('Lancia prima app:setup (servono Antonio e Riccardo).');

            return 1;
        }
        $this->users($io);

        // clienti e veicoli
        $c = [];
        foreach ([
            'bianchi' => ['Bianchi Mario', '333 100 2001', 'SMS'],
            'rossi' => ['Rossi Laura', '333 100 2002', 'WhatsApp'],
            'benaco' => ['Idraulica Benaco Srl', '045 100 2003', 'Telefonata'],
            'ferri' => ['Ferri Giulia', '333 100 2004', 'SMS'],
            'conti' => ['Conti Paolo', '333 100 2005', 'Telefonata'],
            'marchi' => ['Marchi Elena', '333 100 2006', 'SMS'],
            'galli' => ['Galli Stefano', '333 100 2007', 'WhatsApp'],
            'sala' => ['Sala Andrea', '333 100 2008', 'SMS'],
            'lago' => ['Trasporti Lago Snc', '045 100 2009', 'E-mail'],
        ] as $key => [$name, $phone, $pref]) {
            $this->em->persist($c[$key] = (new Customer())->setName($name)->setPhone($phone)->setContactPreference($pref)->setCity('Verona'));
        }
        $v = [];
        foreach ([
            'panda' => ['GH412KT', 'Fiat', 'Panda', '1.2 69 CV', 'Benzina', 2017, 59870, 'bianchi', '+18 months'],
            'golf' => ['FB783ZM', 'Volkswagen', 'Golf', '2.0 TDI 150 CV', 'Diesel', 2016, 148300, 'rossi', '+5 months'],
            'transit' => ['GA219PX', 'Ford', 'Transit Custom', '2.0 EcoBlue 130 CV', 'Diesel', 2020, 187600, 'benaco', '+9 months'],
            'yaris' => ['GN004RS', 'Toyota', 'Yaris Hybrid', '1.5 116 CV', 'Ibrida', 2022, 38220, 'ferri', '+20 months'],
            'audi' => ['FL556DW', 'Audi', 'A4 Avant', '2.0 TDI 190 CV', 'Diesel', 2017, 121040, 'conti', '+3 months'],
            'clio' => ['EZ908HG', 'Renault', 'Clio', '1.2 75 CV', 'Benzina', 2015, 112450, 'marchi', '+10 days'],
            'jeep' => ['GE331LB', 'Jeep', 'Renegade', '1.6 Multijet 120 CV', 'Diesel', 2021, 64300, 'galli', '+14 months'],
            'p208' => ['FX120NC', 'Peugeot', '208', '1.2 PureTech 100 CV', 'Benzina', 2019, 77100, 'sala', '+7 months'],
            'ducato' => ['FR644VA', 'Fiat', 'Ducato', '2.3 Multijet 140 CV', 'Diesel', 2018, 211800, 'lago', '+4 months'],
        ] as $key => [$plate, $make, $model, $version, $fuel, $year, $km, $owner, $revision]) {
            $this->em->persist($v[$key] = (new Vehicle())->setPlate($plate)->setMake($make)->setModel($model)->setVersion($version)
                ->setFuel($fuel)->setYear($year)->setKm($km)->setOwner($c[$owner])->setRevisionDue(new \DateTime($revision)));
        }

        $an = $this->mech['Antonio'];
        $ri = $this->mech['Riccardo'];
        $number = 181;

        // consegnati nei giorni scorsi (per il report ore della settimana)
        $o = $this->order(++$number, $v['ducato'], $c['lago'], -3, 'Tagliando e ammortizzatori posteriori che battono.', 211800);
        $this->task($o, 'Tagliando', $an, Task::DONE, [[-3, '08:10', '10:05']]);
        $this->task($o, 'Sostituzione ammortizzatori posteriori', $ri, Task::DONE, [[-3, '10:20', '12:15'], [-3, '14:00', '14:40']], 150);
        $this->part($o, 'Ammortizzatori posteriori', 2, 'pz', 78.5);
        $o->setStatus(WorkOrder::DELIVERED)->setReadyAt($this->at(-3, '14:45'))->setDeliveredAt($this->at(-3, '17:30'));

        $o = $this->order(++$number, $v['p208'], $c['sala'], -1, 'L\'aria condizionata non raffredda più.', 77050);
        $this->task($o, 'Ricarica climatizzatore', $ri, Task::DONE, [[-1, '09:00', '09:52']]);
        $o->setStatus(WorkOrder::DELIVERED)->setReadyAt($this->at(-1, '09:55'))->setDeliveredAt($this->at(-1, '12:10'));

        // pronta: lavorata ieri, collaudo stamattina
        $o = $this->order(++$number, $v['audi'], $c['conti'], -2, 'Distribuzione da fare a 120.000, e controllare perdita di liquido.', 121040, '17:00');
        $this->task($o, 'Kit distribuzione', $an, Task::DONE, [[-1, '08:05', '12:30'], [-1, '14:00', '15:10']], 240);
        $this->task($o, 'Collaudo e prova su strada', $an, Task::DONE, [[0, '08:00', '08:45']], 45);
        $this->part($o, 'Kit distribuzione con pompa acqua', 1, 'set', 189.0, 'KTBWP7880');
        $this->part($o, 'Liquido refrigerante', 5, 'l', 6.2);
        $o->setStatus(WorkOrder::READY)->setReadyAt($this->at(0, '08:45'))->setCustomerNotifiedAt($this->at(-1, '18:10'));

        // in attesa di ricambi
        $o = $this->order(++$number, $v['transit'], $c['benaco'], -1, 'La frizione slitta e vibra in partenza.', 187600, null, 3);
        $this->task($o, 'Smontaggio frizione', $ri, Task::DONE, [[0, '08:00', '09:30']], 90);
        $this->task($o, 'Montaggio frizione e volano', $ri, Task::TODO, [], 210);
        $this->task($o, 'Prova su strada', $ri, Task::TODO, [], 20);
        $this->part($o, 'Volano bimassa LuK', 1, 'pz', 412.0, '415063610', Part::ORDERED, '+3 days', 'Autoricambi Garda');
        $this->part($o, 'Kit frizione', 1, 'set', 268.0, '624320609', Part::ORDERED, '+3 days', 'Autoricambi Garda');
        $o->setStatus(WorkOrder::WAITING)->setWaitingReason('Attesa ricambi · volano bimassa in arrivo');

        // in lavorazione adesso: Antonio sul tagliando della Panda
        $o = $this->order(++$number, $v['panda'], $c['bianchi'], -1, 'Tagliando dei 60.000. Quando freno piano davanti fischia.', 59870, '18:00');
        $o->setQuoteAmount(258.20)->setIntakeNotes('Graffio sul paraurti posteriore destro già presente. Seggiolino bimbo in auto.');
        $t = $this->task($o, 'Tagliando', $an, Task::RUNNING, [[-110, -60, 'Attesa ricambi'], [-50, null]], 120);
        $t->toggleCheck(0);
        $t->toggleCheck(1);
        $t->toggleCheck(2);
        $t->toggleCheck(3);
        $this->task($o, 'Pastiglie freni anteriori', $an, Task::TODO, [], 60, $this->later(30));
        $this->propose($o, 'Spazzole tergicristallo usurate, lasciano righe: consiglio di sostituirle', $an, -28, 10);
        $this->part($o, 'Olio motore 5W-30', 4, 'l', 9.5, 'SEL-5W30', Part::STOCK, null, null, $t);
        $this->part($o, 'Filtro olio', 1, 'pz', 9.8, null, Part::STOCK, null, null, $t);
        $this->part($o, 'Filtro aria', 1, 'pz', 14.5, null, Part::STOCK, null, null, $t);
        $this->part($o, 'Filtro abitacolo', 1, 'pz', 18.9, null, Part::STOCK, null, null, $t);
        $this->part($o, 'Pastiglie freno anteriori Brembo', 1, 'set', 42.0, 'P23138', Part::ARRIVED);
        $o->setStatus(WorkOrder::IN_PROGRESS);

        // in lavorazione adesso: Riccardo sulla diagnosi della Golf
        $o = $this->order(++$number, $v['golf'], $c['rossi'], 0, 'Si è accesa la spia motore e in salita tira poco.', 148300, null, 1);
        $this->task($o, 'Diagnosi elettronica', $ri, Task::RUNNING, [[-65, null]], 90);
        $this->propose($o, 'Sostituzione valvola EGR (intasata): preventivo 420 €', $ri, -20, 120, $this->later(150));
        $o->setStatus(WorkOrder::IN_PROGRESS);

        // da iniziare
        $o = $this->order(++$number, $v['yaris'], $c['ferri'], 0, 'Montare le invernali, le estive restano in deposito.', 38220, '15:30');
        $this->task($o, 'Cambio gomme stagionale', $an, Task::TODO, [], 60, $this->later(150));

        $o = $this->order(++$number, $v['clio'], $c['marchi'], 0, 'Revisione in scadenza: controllare tutto prima della motorizzazione.', 112450, '18:00');
        $this->task($o, 'Pre-revisione', null, Task::TODO, [], 60);

        $o = $this->order(++$number, $v['jeep'], $c['galli'], 0, 'Rumore dalla sospensione anteriore sulle buche.', 64300, null, 2);
        $t = (new Task())->setTitle('Diagnosi rumore sospensione anteriore')->setMechanic($ri)->setEstimatedMinutes(90)
            ->setChecklistLabels(['Prova su strada', 'Controllo testine e bracci', 'Controllo ammortizzatori e supporti', 'Esito passato all\'ufficio']);
        $o->addTask($t);

        $this->em->flush();
        $io->success('Dati dimostrativi caricati: 9 ordini di lavoro, Antonio e Riccardo al lavoro adesso.');
        $io->writeln('Utenti: ufficio / demo (ufficio), officina / demo (tablet in officina).');

        return 0;
    }

    private function users(SymfonyStyle $io): void
    {
        $roles = $this->em->getRepository(Role::class);
        foreach ([['ufficio', 'Ufficio', 'Accettazione', Role::OFFICE], ['officina', 'Tablet', 'Officina', Role::TABLET]] as [$username, $name, $surname, $role]) {
            if ($this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
                continue;
            }
            $user = (new User())->setUsername($username)->setName($name)->setSurname($surname);
            $user->setPassword($this->hasher->hashPassword($user, 'demo'));
            $user->addCustomrole($roles->findOneBy(['code' => $role]));
            $this->em->persist($user);
            $io->writeln("Utente $username creato");
        }
    }

    /** Giorno relativo a oggi alle hh:mm */
    private function at(int $days, string $time): \DateTime
    {
        [$h, $m] = explode(':', $time);

        return \DateTime::createFromImmutable($this->today->modify("$days days")->setTime((int) $h, (int) $m));
    }

    /** Fra $minutes minuti, arrotondato al quarto d'ora */
    private function later(int $minutes): \DateTime
    {
        $t = $this->now->modify("+$minutes minutes");
        $m = (int) ceil(((int) $t->format('i')) / 15) * 15;

        return \DateTime::createFromImmutable($t->setTime((int) $t->format('G'), 0)->modify("+$m minutes"));
    }

    private function order(int $number, Vehicle $vehicle, Customer $customer, int $acceptedDay, string $request, int $km, ?string $promisedTime = null, ?int $promisedDays = null): WorkOrder
    {
        $order = (new WorkOrder())->setNumber($number)->setVehicle($vehicle)->setCustomer($customer)
            ->setAcceptedAt($this->at($acceptedDay, $acceptedDay < 0 ? '17:45' : '07:55'))
            ->setCustomerRequest($request)->setKmIn($km)->setFuelLevel(['1/4', '1/2', '3/4'][$number % 3]);
        if ($promisedTime) {
            $order->setPromisedAt($this->at(0, $promisedTime));
        } elseif ($promisedDays !== null) {
            $day = $this->today;
            for ($i = 0; $i < $promisedDays; $i++) {
                do {
                    $day = $day->modify('+1 day');
                } while ((int) $day->format('N') >= 6);
            }
            $order->setPromisedAt(\DateTime::createFromImmutable($day->setTime(18, 0)));
        }
        $this->em->persist($order);

        return $order;
    }

    /**
     * @param array $entries registrazioni: [giorno, 'hh:mm', 'hh:mm'] oppure [minuti da adesso, minuti da adesso|null, motivo]
     */
    private function task(WorkOrder $order, string $title, ?Mechanic $mechanic, string $status, array $entries, ?int $minutes = null, ?\DateTime $planned = null): Task
    {
        $task = isset($this->tpl[$title]) ? Task::fromTemplate($this->tpl[$title]) : (new Task())->setTitle($title);
        $task->setMechanic($mechanic)->setStatus($status)->setPlannedStart($planned);
        if ($minutes) {
            $task->setEstimatedMinutes($minutes);
        }
        foreach ($entries as $e) {
            if (is_string($e[1])) {
                $entry = new TimeEntry($mechanic, $this->at($e[0], $e[1]));
                $entry->close($status === Task::DONE && $e === end($entries) ? 'Completata' : 'Fine giornata', $this->at($e[0], $e[2]));
            } else {
                $entry = new TimeEntry($mechanic, \DateTime::createFromImmutable($this->now->modify($e[0].' minutes')));
                if ($e[1] !== null) {
                    $entry->close($e[2] ?? 'Altro', \DateTime::createFromImmutable($this->now->modify($e[1].' minutes')));
                }
            }
            $task->addTimeEntry($entry);
        }
        if ($status === Task::DONE) {
            foreach (array_keys($task->getChecklist()) as $i) {
                $task->toggleCheck($i);
            }
            $last = $task->getTimeEntries()->last();
            $task->setCompletedAt($last ? $last->getEndedAt() : $this->now);
        }
        $order->addTask($task);

        return $task;
    }

    private function propose(WorkOrder $order, string $title, Mechanic $mechanic, int $minutesAgo, int $estimate, ?\DateTime $planned = null): void
    {
        $order->addTask((new Task())->setTitle($title)->setStatus(Task::PROPOSED)->setMechanic($mechanic)->setProposedBy($mechanic)
            ->setProposedAt(\DateTime::createFromImmutable($this->now->modify("$minutesAgo minutes")))->setEstimatedMinutes($estimate)->setPlannedStart($planned));
    }

    private function part(WorkOrder $order, string $description, float $qty, string $unit, ?float $price, ?string $code = null, string $status = Part::STOCK, ?string $expected = null, ?string $supplier = null, ?Task $task = null): void
    {
        $order->addPart((new Part())->setDescription($description)->setQuantity($qty)->setUnit($unit)->setUnitPrice($price)->setCode($code)
            ->setStatus($status)->setExpectedAt($expected ? new \DateTime($expected) : null)->setSupplier($supplier)->setTask($task));
    }
}
