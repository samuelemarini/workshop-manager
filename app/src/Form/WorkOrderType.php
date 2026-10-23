<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\TaskTemplate;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Accettazione (nuovo ordine) e modifica dell'ordine. In accettazione veicolo e cliente si possono
 * scegliere dall'anagrafica oppure scrivere al volo (campi "new*", non mappati: li crea il controller).
 */
class WorkOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['acceptance'];

        $builder
            ->add('vehicle', EntityType::class, [
                'label' => 'Veicolo',
                'class' => Vehicle::class,
                'required' => !$isNew,
                'placeholder' => $isNew ? 'Veicolo nuovo (compila sotto)' : '—',
                'query_builder' => fn ($r) => $r->createQueryBuilder('v')->leftJoin('v.owner', 'o')->addSelect('o')->where('v.cancel = 0')->orderBy('v.plate'),
                'choice_attr' => fn (Vehicle $v) => ['data-owner' => $v->getOwner()?->getId(), 'data-km' => $v->getKm(), 'data-subtext' => $v->getOwner()?->getName()],
                'attr' => ['class' => 'selectpicker', 'data-live-search' => 'true', 'data-vehicle-select' => ''],
            ])
            ->add('customer', EntityType::class, [
                'label' => 'Cliente',
                'class' => Customer::class,
                'required' => !$isNew,
                'placeholder' => $isNew ? 'Cliente nuovo (compila sotto)' : '—',
                'query_builder' => fn ($r) => $r->createQueryBuilder('c')->where('c.cancel = 0')->orderBy('c.name'),
                'choice_attr' => fn (Customer $c) => ['data-subtext' => $c->getPhone()],
                'attr' => ['class' => 'selectpicker', 'data-live-search' => 'true', 'data-customer-select' => ''],
            ])
            ->add('promisedAt', DateTimeType::class, ['label' => 'Consegna promessa', 'required' => false, 'widget' => 'single_text'])
            ->add('kmIn', IntegerType::class, ['label' => 'Km in ingresso', 'required' => false])
            ->add('fuelLevel', ChoiceType::class, ['label' => 'Carburante', 'required' => false, 'placeholder' => '—', 'choices' => array_combine(WorkOrder::FUEL_LEVELS, WorkOrder::FUEL_LEVELS)])
            ->add('courtesyCar', CheckboxType::class, ['label' => 'Auto di cortesia', 'required' => false])
            ->add('customerRequest', TextareaType::class, ['label' => 'Cosa chiede il cliente', 'required' => false, 'attr' => ['rows' => 3, 'placeholder' => 'Con le sue parole: lo leggono anche i meccanici sul tablet']])
            ->add('intakeNotes', TextareaType::class, ['label' => 'Stato all\'ingresso', 'required' => false, 'attr' => ['rows' => 2, 'placeholder' => 'Danni già presenti, oggetti lasciati in auto...']])
            ->add('quoteAmount', MoneyType::class, ['label' => 'Preventivo accettato (IVA esclusa)', 'required' => false, 'currency' => 'EUR', 'html5' => true, 'attr' => ['step' => '0.01']]);

        if ($isNew) {
            $builder
                ->add('newPlate', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Targa', 'attr' => ['class' => 'text-uppercase', 'autocomplete' => 'off', 'data-plate-lookup' => '']])
                ->add('newMake', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Marca'])
                ->add('newModel', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Modello'])
                ->add('newVersion', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Motorizzazione'])
                ->add('newYear', IntegerType::class, ['mapped' => false, 'required' => false, 'label' => 'Anno'])
                ->add('newCustomerName', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Nome e cognome o ragione sociale'])
                ->add('newCustomerPhone', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Telefono'])
                ->add('templates', EntityType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Lavorazioni da fare',
                    'class' => TaskTemplate::class,
                    'multiple' => true,
                    'expanded' => true,
                    'query_builder' => fn ($r) => $r->createQueryBuilder('t')->where('t.cancel = 0')->orderBy('t.sort')->addOrderBy('t.name'),
                    'choice_label' => fn (TaskTemplate $t) => $t->getName(),
                ])
                ->add('otherTask', TextType::class, ['mapped' => false, 'required' => false, 'label' => 'Altro da fare', 'attr' => ['placeholder' => 'es. Controllare rumore sospensione anteriore']]);
        } else {
            $builder->add('internalNotes', TextareaType::class, ['label' => 'Note interne', 'required' => false, 'attr' => ['rows' => 3]]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // in accettazione veicolo e cliente possono mancare: li crea il controller dai campi "new*"
        $resolver->setDefaults([
            'data_class' => WorkOrder::class,
            'acceptance' => false,
            'validation_groups' => fn ($form) => $form->getConfig()->getOption('acceptance') ? ['Default'] : ['Default', 'complete'],
        ]);
        $resolver->setAllowedTypes('acceptance', 'bool');
    }
}
