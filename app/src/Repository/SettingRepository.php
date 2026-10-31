<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Parametri dell'officina con i valori di default (usati finché non si salvano dalle impostazioni).
 *
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public const DEFAULTS = [
        'workshop_name' => '',
        'labour_rate' => '45',
        'day_start' => '08:00',
        'day_end' => '18:00',
        'lunch_start' => '12:30',
        'lunch_end' => '14:00',
    ];

    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = self::DEFAULTS;
            foreach ($this->findAll() as $setting) {
                $this->cache[$setting->getName()] = $setting->getValue();
            }
        }

        return $this->cache;
    }

    public function get(string $name): string
    {
        return $this->all()[$name] ?? '';
    }

    /** Persiste soltanto: il flush è del chiamante */
    public function set(string $name, string $value): void
    {
        $setting = $this->find($name) ?? new Setting($name, $value);
        $setting->setValue($value);
        $this->getEntityManager()->persist($setting);
        $this->cache = null;
    }

    public function labourRate(): float
    {
        return (float) str_replace(',', '.', $this->get('labour_rate'));
    }

    /** Minuti dalla mezzanotte di un orario "HH:MM" delle impostazioni */
    public function minutes(string $name): int
    {
        [$h, $m] = array_map('intval', explode(':', $this->get($name).':0'));

        return $h * 60 + $m;
    }
}
