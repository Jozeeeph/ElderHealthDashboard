<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\TypeEvent;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('dateDebut', null, [
                'widget' => 'single_text',
            ])
            ->add('dateFin', null, [
                'widget' => 'single_text',
            ])
            ->add('lieu')
            ->add('capaciteMax')

            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Privée' => 'PRIVEE',
                    'Publiée' => 'PUBLIE',
                ],
                'placeholder' => 'Choisir un statut',
            ])

            ->add('image', FileType::class, [
                'label' => 'Image (PNG, JPG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/jpg',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG/PNG).',
                    ])
                ],
            ])



            ->add('type', EntityType::class, [
                'class' => TypeEvent::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir un type',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('t')
                        ->andWhere('t.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('t.libelle', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
