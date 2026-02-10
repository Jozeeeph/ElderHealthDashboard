<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PatientRendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date du rendez-vous',
            ])
            ->add('heure', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
            ])
            ->add('personnelMedical', EntityType::class, [
                'class' => Utilisateur::class,
                'query_builder' => function (UtilisateurRepository $repo) {
                    return $repo->createQueryBuilder('u')
                        ->andWhere('u.role = :role')
                        ->setParameter('role', 'PERSONNEL_MEDICAL')
                        ->orderBy('u.nom', 'ASC');
                },
                'choice_label' => function (Utilisateur $u) {
                    return trim($u->getNom() . ' ' . $u->getPrenom());
                },
                'placeholder' => 'Choisir un personnel medical',
                'label' => 'Personnel medical',
            ])
            ->add('typeRendezVous', EntityType::class, [
                'class' => TypeRendezVous::class,
                'choice_label' => 'type',
                'placeholder' => 'Choisir un type de rendez-vous',
                'label' => 'Type de rendez-vous',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
