<?php

namespace App\Form\gestionRendezVous;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use App\Entity\Patient;
use App\Entity\PersonnelMedical;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date du rendez-vous'
            ])
            ->add('heure', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure'
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu'
            ])
            ->add('patient', EntityType::class, [
                'class' => Patient::class,
                'choice_label' => 'id', // à remplacer par nom/prénom si dispo
                'label' => 'Patient'
            ])
            ->add('personnelMedical', EntityType::class, [
                'class' => PersonnelMedical::class,
                'choice_label' => 'id',
                'label' => 'Personnel médical'
            ])
            ->add('typeRendezVous', EntityType::class, [
                'class' => TypeRendezVous::class,
                'choice_label' => 'type', // 🔥 champ affiché
                'placeholder' => '--- Choisir un type ---',
                'label' => 'Type de rendez-vous'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
