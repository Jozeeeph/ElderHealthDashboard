<?php

namespace App\Form;

use App\Entity\TypeRendezVous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class TypeRendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class, [
                'label' => 'Type de rendez-vous',
            ])
            ->add('Tarif', NumberType::class, [
                'label' => 'Tarif (DT)',
                'scale' => 2,
            ])
            ->add('Durée', TextType::class, [
                'label' => 'Durée (minutes)',
            ]);
            
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeRendezVous::class,
        ]);
    }
}
