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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "L'email est obligatoire."]),
                    new Assert\Email(['message' => "Veuillez saisir un email valide."]),
                    new Assert\Length(['max' => 180]),
                ],
            ])
            ->add('nom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le nom est obligatoire."]),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                    new Assert\Regex([
                        'pattern' => "/^[\p{L}\s'\-]+$/u",
                        'message' => "Le nom doit contenir uniquement des lettres."
                    ]),
                ],
            ])
            ->add('prenom', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le prénom est obligatoire."]),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                    new Assert\Regex([
                        'pattern' => "/^[\p{L}\s'\-]+$/u",
                        'message' => "Le prénom doit contenir uniquement des lettres."
                    ]),
                ],
            ])
            ->add('adresse', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "L'adresse est obligatoire."]),
                    new Assert\Length(['min' => 5, 'max' => 255]),
                ],
            ])
            ->add('age', IntegerType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "L'âge est obligatoire."]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => 120,
                        'notInRangeMessage' => "L'âge doit être entre {{ min }} et {{ max }}."
                    ]),
                ],
            ])
            ->add('dateNaissance', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(['message' => "La date de naissance est obligatoire."]),
                    new Assert\LessThanOrEqual([
                        'value' => 'today',
                        'message' => "La date de naissance ne peut pas être dans le futur."
                    ]),
                ],
            ])
            ->add('numeroTelephone', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le numéro de téléphone est obligatoire."]),
                    new Assert\Regex([
                        'pattern' => "/^\+?\d{8,15}$/",
                        'message' => "Numéro invalide. Exemple: 22123456 ou +21622123456."
                    ]),
                ],
            ])
            ->add('cin', TextType::class, [
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le CIN est obligatoire."]),
                    new Assert\Regex([
                        'pattern' => "/^\d{8}$/",
                        'message' => "Le CIN doit contenir exactement 8 chiffres."
                    ]),
                ],
            ])

            ->add('roleMetier', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'Patient' => Role::PATIENT->value,
                    'Personnel médical' => Role::PERSONNEL_MEDICAL->value,
                    'Propriétaire médicaux' => Role::PROPRIETAIRE_MEDICAUX->value,
                ],
                'placeholder' => 'Choisir un rôle',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Veuillez choisir un rôle."]),
                    new Assert\Choice([
                        'choices' => [
                            Role::PATIENT->value,
                            Role::PERSONNEL_MEDICAL->value,
                            Role::PROPRIETAIRE_MEDICAUX->value,
                        ],
                        'message' => "Rôle invalide.",
                    ]),
                ],
            ])

            ->add('dossierMedicalFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF.',
                    ])
                ],
            ])
            ->add('cvFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
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
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF.',
                    ])
                ],
            ])

            ->add('hopitalAffectation', TextType::class, ['required' => false])
            ->add('nbAnneeExperience', IntegerType::class, ['required' => false])
            ->add('specialite', TextType::class, ['required' => false])
            ->add('fonction', TextType::class, ['required' => false])

            ->add('patante', TextType::class, ['required' => false])
            ->add('numeroFix', TextType::class, ['required' => false])

            // ✅ mot de passe "clair" validé ici (PAS dans l’Entity)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => "Le mot de passe est obligatoire."]),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => "Le mot de passe doit contenir au moins {{ limit }} caractères."
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/",
                        'message' => "Mot de passe trop faible (maj, min, chiffre, symbole)."
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
