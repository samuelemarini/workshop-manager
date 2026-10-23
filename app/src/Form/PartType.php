<?php

namespace App\Form;

use App\Entity\Part;
use App\Entity\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $order = $options['work_order'];

        $builder
            ->add('code', TextType::class, ['label' => 'Codice', 'required' => false])
            ->add('description', TextType::class, ['label' => 'Descrizione'])
            ->add('quantity', NumberType::class, ['label' => 'Quantità', 'html5' => true, 'scale' => 2, 'attr' => ['step' => '0.5', 'min' => 0]])
            ->add('unit', ChoiceType::class, ['label' => 'Unità', 'choices' => array_combine(Part::UNITS, Part::UNITS)])
            ->add('unitPrice', MoneyType::class, ['label' => 'Prezzo unitario', 'required' => false, 'currency' => 'EUR', 'html5' => true, 'attr' => ['step' => '0.01']])
            ->add('status', ChoiceType::class, ['label' => 'Provenienza', 'choices' => array_flip(Part::STATUSES)])
            ->add('supplier', TextType::class, ['label' => 'Fornitore', 'required' => false])
            ->add('expectedAt', DateType::class, ['label' => 'Arrivo previsto', 'required' => false, 'widget' => 'single_text'])
            ->add('task', EntityType::class, [
                'label' => 'Per la lavorazione',
                'class' => Task::class,
                'required' => false,
                'placeholder' => '—',
                'choices' => $order ? $order->getTasks()->toArray() : [],
                'choice_label' => 'title',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Part::class, 'work_order' => null]);
    }
}
