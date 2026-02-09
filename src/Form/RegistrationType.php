<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // standard
            ->add('email', EmailType::class)
            ->add('nom', TextType::class)
            ->add('prenom', TextType::class)
            ->add('adresse', TextType::class)
            ->add('age', IntegerType::class)
            ->add('dateNaissance', DateType::class, ['widget' => 'single_text'])
            ->add('numeroTelephone', TextType::class)
            ->add('cin', TextType::class)

            // choix du rôle
            ->add('roleMetier', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'Patient' => Role::PATIENT->value,
                    'Personnel médical' => Role::PERSONNEL_MEDICAL->value,
                    'Propriétaire médicaux' => Role::PROPRIETAIRE_MEDICAUX->value,
                ],
                'placeholder' => 'Choisir un rôle',
                'required' => true,
            ])

            // Patient: pdf dossier médical
            ->add('dossierMedicalFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF.',
                    ])
                ],
            ])

            // Personnel médical: CV + certification + champs texte
            ->add('cvFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF.',
                    ])
                ],
            ])
            ->add('certificationFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF.',
                    ])
                ],
            ])
            ->add('hopitalAffectation', TextType::class, ['required' => false])
            ->add('nbAnneeExperience', IntegerType::class, ['required' => false])
            ->add('specialite', TextType::class, ['required' => false])
            ->add('fonction', TextType::class, ['required' => false])

            // Propriétaire médicaux
            ->add('patante', TextType::class, ['required' => false])
            ->add('numeroFix', TextType::class, ['required' => false])

            ->add('password', PasswordType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
