<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nome e cognome o ragione sociale'])
            ->add('vatNumber', TextType::class, ['label' => 'Codice fiscale / P. IVA', 'required' => false])
            ->add('address', TextType::class, ['label' => 'Indirizzo', 'required' => false])
            ->add('zip', TextType::class, ['label' => 'CAP', 'required' => false])
            ->add('city', TextType::class, ['label' => 'Città', 'required' => false])
            ->add('country', TextType::class, ['label' => 'Paese', 'required' => false])
            ->add('contactPerson', TextType::class, ['label' => 'Referente (aziende)', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Telefono', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false])
            ->add('contactPreference', ChoiceType::class, [
                'label' => 'Avvisarlo con',
                'required' => false,
                'placeholder' => '—',
                'choices' => array_combine(Customer::CONTACT_PREFERENCES, Customer::CONTACT_PREFERENCES),
            ])
            ->add('notes', TextareaType::class, ['label' => 'Note', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Customer::class]);
    }
}
