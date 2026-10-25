<?php

namespace App\Form;

use App\Entity\Mechanic;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $builder->getData()?->getId() === null;

        if ($isNew && !$options['plan_only']) {
            // scegliendo una lavorazione tipo, il JavaScript precompila titolo, tempo e controlli
            $builder->add('template', EntityType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Lavorazione tipo',
                'placeholder' => '— scrivi a mano —',
                'class' => TaskTemplate::class,
                'query_builder' => fn ($r) => $r->createQueryBuilder('t')->where('t.cancel = 0')->orderBy('t.sort')->addOrderBy('t.name'),
                'choice_attr' => fn (TaskTemplate $t) => ['data-minutes' => $t->getEstimatedMinutes(), 'data-checklist' => $t->getChecklistText()],
                'attr' => ['data-template-select' => ''],
            ]);
        }
        if (!$options['plan_only']) {
            $builder
                ->add('title', TextType::class, ['label' => 'Lavorazione'])
                ->add('description', TextareaType::class, ['label' => 'Dettagli per il meccanico', 'required' => false, 'attr' => ['rows' => 2]]);
        }
        $builder
            ->add('mechanic', EntityType::class, [
                'label' => 'Meccanico',
                'class' => Mechanic::class,
                'required' => false,
                'placeholder' => 'Da assegnare',
                'query_builder' => fn ($r) => $r->createQueryBuilder('m')->where('m.cancel = 0')->orderBy('m.sort')->addOrderBy('m.name'),
            ])
            ->add('plannedStart', DateTimeType::class, ['label' => 'Pianificata per', 'required' => false, 'widget' => 'single_text', 'help' => 'Vuoto = da pianificare'])
            ->add('estimatedMinutes', IntegerType::class, ['label' => 'Tempo stimato (minuti)', 'attr' => ['min' => 0, 'step' => 5]]);
        if (!$options['plan_only']) {
            $builder->add('checklistText', TextareaType::class, ['label' => 'Controlli da spuntare sul tablet (uno per riga)', 'required' => false, 'attr' => ['rows' => 5]]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Task::class, 'plan_only' => false]);
        $resolver->setAllowedTypes('plan_only', 'bool');
    }
}
