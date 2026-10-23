<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Vehicle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VehicleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plate', TextType::class, ['label' => 'Targa', 'attr' => ['class' => 'text-uppercase', 'autocomplete' => 'off']])
            ->add('make', TextType::class, ['label' => 'Marca'])
            ->add('model', TextType::class, ['label' => 'Modello'])
            ->add('version', TextType::class, ['label' => 'Motorizzazione', 'required' => false, 'attr' => ['placeholder' => 'es. 1.2 69 CV']])
            ->add('fuel', ChoiceType::class, ['label' => 'Alimentazione', 'required' => false, 'placeholder' => '—', 'choices' => array_combine(Vehicle::FUELS, Vehicle::FUELS)])
            ->add('year', IntegerType::class, ['label' => 'Anno', 'required' => false])
            ->add('vin', TextType::class, ['label' => 'Telaio (VIN)', 'required' => false])
            ->add('km', IntegerType::class, ['label' => 'Km', 'required' => false])
            ->add('revisionDue', DateType::class, ['label' => 'Scadenza revisione', 'required' => false, 'widget' => 'single_text'])
            ->add('owner', EntityType::class, [
                'label' => 'Proprietario',
                'class' => Customer::class,
                'required' => false,
                'placeholder' => '—',
                'query_builder' => fn ($r) => $r->createQueryBuilder('c')->where('c.cancel = 0')->orderBy('c.name'),
                'attr' => ['class' => 'selectpicker', 'data-live-search' => 'true'],
            ])
            ->add('notes', TextareaType::class, ['label' => 'Note', 'required' => false, 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Vehicle::class]);
    }
}
