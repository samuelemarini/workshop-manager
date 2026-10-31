<?php

namespace App\Form;

use App\Entity\Mechanic;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

class MechanicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mechanic = $builder->getData();

        $builder
            ->add('name', TextType::class, ['label' => 'Nome'])
            ->add('surname', TextType::class, ['label' => 'Cognome', 'required' => false])
            ->add('color', ChoiceType::class, ['label' => 'Colore', 'choices' => array_flip(Mechanic::COLORS), 'expanded' => true, 'attr' => ['class' => 'choice-pills color-pills']])
            ->add('dailyHours', NumberType::class, ['label' => 'Ore al giorno', 'html5' => true, 'scale' => 2, 'attr' => ['step' => '0.5', 'min' => 0, 'max' => 24]])
            ->add('sort', IntegerType::class, ['label' => 'Ordine in elenco', 'required' => false, 'empty_data' => '0'])
            ->add('pin', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => $mechanic?->hasPin() ? 'Nuovo PIN' : 'PIN per il tablet',
                'help' => $mechanic?->hasPin() ? 'Vuoto = resta quello attuale' : 'Facoltativo, 4 cifre: senza PIN si entra con un tocco',
                'constraints' => [new Regex('/^\d{4}$/', 'Il PIN è di 4 cifre')],
                'attr' => ['inputmode' => 'numeric', 'maxlength' => 4, 'autocomplete' => 'off'],
            ]);
        if ($mechanic?->hasPin()) {
            $builder->add('removePin', CheckboxType::class, ['mapped' => false, 'required' => false, 'label' => 'Togli il PIN']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Mechanic::class]);
    }
}
