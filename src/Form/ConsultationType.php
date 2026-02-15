<?php

namespace App\Form;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeConsultation', ChoiceType::class, [
                'choices' => [
                    'Consultation generale' => 'consultation_generale',
                    'Suivi' => 'suivi',
                    'Urgence' => 'urgence',
                    'Teleconsultation' => 'teleconsultation',
                ],
                'placeholder' => 'Selectionner un type',
            ])
            ->add('dateConsultation', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('heureConsultation', TimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('lieu', TextType::class)
            ->add('poidsKg', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['step' => '0.1', 'min' => 20, 'max' => 300],
            ])
            ->add('tensionSystolique', IntegerType::class, [
                'required' => false,
                'attr' => ['min' => 60, 'max' => 250],
            ])
            ->add('tensionDiastolique', IntegerType::class, [
                'required' => false,
                'attr' => ['min' => 30, 'max' => 150],
            ])
            ->add('patient', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $u) {
                    return trim($u->getPrenom() . ' ' . $u->getNom());
                },
                'query_builder' => function ($repo) {
                    return $repo->createQueryBuilder('u')
                        ->andWhere('u.role = :role')
                        ->setParameter('role', Role::PATIENT);
                },
            ])
            ->add('personnelMedical', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $u) {
                    return trim($u->getPrenom() . ' ' . $u->getNom());
                },
                'query_builder' => function ($repo) {
                    return $repo->createQueryBuilder('u')
                        ->andWhere('u.role = :role')
                        ->setParameter('role', Role::PERSONNEL_MEDICAL);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Consultation::class,
        ]);
    }
}
