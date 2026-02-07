<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PersonnelMedicalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cv', TextType::class, ['required' => false])
            ->add('certification', TextType::class, ['required' => false])
            ->add('attestation', TextType::class, ['required' => false])
            ->add('hopitalAffectation', TextType::class, ['required' => false])
            ->add('nbAnneeExperience', IntegerType::class, ['required' => false])
            ->add('specialite', TextType::class, ['required' => false])
            ->add('disponibilite', TextType::class, ['required' => false])
            ->add('fonction', TextType::class, ['required' => false]);
    }

    public function getParent(): string
    {
        return UtilisateurBaseType::class;
    }
}
