<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use App\Service\Language;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $builder->getData()?->getId() === null;

        $builder
            ->add('username', TextType::class, ['label' => 'Username', 'attr' => ['readonly' => !$isNew]])
            ->add('name', TextType::class, ['label' => 'Nome'])
            ->add('surname', TextType::class, ['label' => 'Cognome', 'required' => false, 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false])
            // Ogni lingua col suo nome nella lingua stessa: i nomi non si traducono
            ->add('locale', ChoiceType::class, [
                'label' => 'Lingua',
                'required' => false,
                'placeholder' => 'Automatica (lingua del browser)',
                'choices' => array_flip(Language::LANGUAGES),
                'choice_translation_domain' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => $isNew,
                'invalid_message' => 'Le password devono corrispondere',
                'first_options' => ['label' => $isNew ? 'Password' : 'Nuova password', 'required' => $isNew],
                'second_options' => ['label' => 'Ripeti password', 'required' => $isNew],
            ]);

        if ($options['admin']) {
            $builder
                ->add('customroles', EntityType::class, [
                    'label' => 'Ruoli',
                    'class' => Role::class,
                    'choice_label' => 'name',
                    'multiple' => true,
                    'attr' => ['class' => 'selectpicker'],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'admin' => false]);
        $resolver->setAllowedTypes('admin', 'bool');
    }
}
