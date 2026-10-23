<?php

namespace App\Command;

use App\Entity\Mechanic;
use App\Entity\Role;
use App\Entity\TaskTemplate;
use App\Entity\User;
use App\Entity\Workstation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dati iniziali: ruoli, utente amministratore, postazioni, lavorazioni tipo e i meccanici di oggi.
 * Idempotente, si può rilanciare: postazioni, lavorazioni tipo e meccanici si creano solo se la tabella è vuota.
 */
#[AsCommand(name: 'app:setup', description: 'Crea ruoli, amministratore, postazioni, lavorazioni tipo e meccanici di base')]
class SetupCommand
{
    private const ROLES = [
        Role::ADMIN => 'Amministratore',
        Role::OFFICE => 'Ufficio',
        Role::TABLET => 'Tablet officina',
    ];

    private const WORKSTATIONS = [
        ['Ponte 1', 'Sollevatore 3,5 t'],
        ['Ponte 2', 'Sollevatore 5 t · furgoni'],
        ['Ponte 3', 'Diagnosi ed elettronica'],
        ['Smontagomme', 'Equilibratrice e assetto'],
    ];

    // nome, minuti, controlli
    public const TEMPLATES = [
        ['Tagliando', 120, ['Olio motore sostituito', 'Filtro olio', 'Filtro aria', 'Filtro abitacolo', 'Livelli freni, refrigerante, lavavetri', 'Pressione pneumatici', 'Luci e tergicristalli', 'Reset spia service']],
        ['Pastiglie freni anteriori', 60, ['Smontaggio ruote', 'Controllo spessore dischi', 'Sostituzione pastiglie', 'Pulizia e ingrassaggio pinze', 'Livello liquido freni', 'Prova su strada']],
        ['Pastiglie freni posteriori', 60, ['Smontaggio ruote', 'Controllo dischi o tamburi', 'Sostituzione pastiglie', 'Regolazione freno a mano', 'Prova su strada']],
        ['Cambio gomme stagionale', 60, ['Smontaggio e controllo usura', 'Montaggio', 'Equilibratura', 'Pressione e sensori TPMS', 'Serraggio con dinamometrica', 'Etichetta deposito gomme']],
        ['Pre-revisione', 60, ['Luci e fari', 'Freni al banco', 'Gas di scarico', 'Pneumatici', 'Cinture e tergicristalli']],
        ['Diagnosi elettronica', 60, ['Lettura errori centralina', 'Verifica sensori', 'Prova su strada', 'Esito passato all\'ufficio']],
        ['Ricarica climatizzatore', 45, ['Recupero gas', 'Prova di tenuta', 'Ricarica', 'Controllo temperatura bocchette']],
        ['Sostituzione batteria', 30, ['Test batteria e alternatore', 'Sostituzione', 'Reset finestrini e orologio']],
        ['Kit distribuzione', 240, ['Smontaggio', 'Sostituzione cinghia e tenditori', 'Pompa acqua', 'Liquido refrigerante', 'Prova su strada']],
    ];

    private const MECHANICS = [['Antonio', 'red'], ['Riccardo', 'black']];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Password dell\'utente admin (solo alla creazione)')] string $password = 'admin',
        #[Option('Non creare l\'utente admin (produzione senza ADMIN_PASSWORD)')] bool $noAdmin = false,
    ): int
    {
        $roles = $this->em->getRepository(Role::class);
        foreach (self::ROLES as $code => $name) {
            if (!$roles->findOneBy(['code' => $code])) {
                $this->em->persist((new Role())->setCode($code)->setName($name));
                $io->writeln("Ruolo $name creato");
            }
        }
        $this->em->flush();

        if ($this->em->getRepository(Workstation::class)->count([]) === 0) {
            foreach (self::WORKSTATIONS as $i => [$name, $description]) {
                $this->em->persist((new Workstation())->setName($name)->setDescription($description)->setSort(($i + 1) * 10));
            }
            $io->writeln(count(self::WORKSTATIONS).' postazioni create');
        }

        if ($this->em->getRepository(TaskTemplate::class)->count([]) === 0) {
            foreach (self::TEMPLATES as $i => [$name, $minutes, $checklist]) {
                $this->em->persist((new TaskTemplate())->setName($name)->setEstimatedMinutes($minutes)->setChecklist($checklist)->setSort(($i + 1) * 10));
            }
            $io->writeln(count(self::TEMPLATES).' lavorazioni tipo create');
        }

        if ($this->em->getRepository(Mechanic::class)->count([]) === 0) {
            foreach (self::MECHANICS as $i => [$name, $color]) {
                $this->em->persist((new Mechanic())->setName($name)->setColor($color)->setSort(($i + 1) * 10));
            }
            $io->writeln(count(self::MECHANICS).' meccanici creati');
        }

        if (!$noAdmin && !$this->em->getRepository(User::class)->findOneBy(['username' => 'admin'])) {
            $admin = (new User())->setUsername('admin')->setName('Amministratore')->setSurname('');
            $admin->setPassword($this->hasher->hashPassword($admin, $password));
            $admin->addCustomrole($roles->findOneBy(['code' => Role::ADMIN]));
            $this->em->persist($admin);
            $io->writeln('Utente admin creato');
        }
        $this->em->flush();

        $io->success('Setup completato');

        return 0;
    }
}
