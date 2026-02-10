<?php

namespace App\Form;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('typeConsultation', ChoiceType::class, [
                'choices' => [
                    'Consultation gÃ©nÃ©rale' => 'consultation_generale',
                    'Suivi' => 'suivi',
                    'Urgence' => 'urgence',
                    'TÃ©lÃ©consultation' => 'teleconsultation',
                ],
                'placeholder' => 'SÃ©lectionner un type',
            ])
            ->add('dateConsultation', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('heureConsultation', TimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('lieu', TextType::class)
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Consultation::class,
        ]);
    }
}
