<?php

namespace App\Form;

use App\Entity\TaskTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nome'])
            ->add('estimatedMinutes', IntegerType::class, ['label' => 'Tempo stimato (minuti)', 'attr' => ['min' => 0, 'step' => 5]])
            ->add('sort', IntegerType::class, ['label' => 'Ordine in elenco', 'required' => false, 'empty_data' => '0'])
            ->add('checklistText', TextareaType::class, ['label' => 'Controlli da spuntare (uno per riga)', 'required' => false, 'attr' => ['rows' => 8]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TaskTemplate::class]);
    }
}
