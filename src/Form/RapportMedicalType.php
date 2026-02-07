<?php

namespace App\Form;

use App\Entity\RapportMedical;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RapportMedicalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('diagnostic', TextareaType::class, [
                'label' => 'Diagnostic',
                'attr' => ['rows' => 4],
            ])
            ->add('recommandations', TextareaType::class, [
                'label' => 'Recommandations',
                'attr' => ['rows' => 4],
            ])
            ->add('niveauGravite', ChoiceType::class, [
                'label' => 'Niveau de gravite',
                'choices' => [
                    'Faible' => 'faible',
                    'Moyen' => 'moyen',
                    'Eleve' => 'eleve',
                ],
                'placeholder' => 'Choisir un niveau',
            ])
            ->add('fichier', FileType::class, [
                'label' => 'Fichier / Image (PDF, JPG, PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez choisir un fichier PDF ou une image (JPG/PNG/WebP).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RapportMedical::class,
        ]);
    }
}
