<?php

namespace App\Repository;

use App\Entity\TaskTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTemplate>
 */
class TaskTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskTemplate::class);
    }

    /** @return TaskTemplate[] */
    public function findActive(): array
    {
        return $this->findBy(['cancel' => 0], ['sort' => 'ASC', 'name' => 'ASC']);
    }
}
