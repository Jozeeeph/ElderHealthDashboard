<?php

namespace App\Form;

use App\Entity\Prescription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PrescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('medicaments', TextareaType::class, [
                'label' => 'Medicaments',
                'attr' => ['rows' => 4],
            ])
            ->add('frequence', TextType::class, [
                'label' => 'Frequence',
            ])
            ->add('dosage', TextType::class, [
                'label' => 'Dosage',
            ])
            ->add('dureeTraitement', TextType::class, [
                'label' => 'Duree du traitement',
            ])
            ->add('consignes', TextareaType::class, [
                'label' => 'Consignes',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date debut',
                'widget' => 'single_text',
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date fin',
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prescription::class,
        ]);
    }
}
